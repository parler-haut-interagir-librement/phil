<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Receipts;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineReceiptRepository;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Receipts and credit notes issued inside transactions, numbered without gaps, and read back from a fresh unit of
 * work as they were issued.
 */
#[CoversClass(PhilCommerceBundle::class)]
#[CoversClass(DoctrineDocumentNumbering::class)]
#[CoversClass(DoctrineReceiptRepository::class)]
final class ReceiptFlowTest extends FunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $this->createSchema($this->entityManager);
    }

    public function testReceiptsAndCreditNotesAreNumberedInTheirSeries(): void
    {
        $receipts = $this->service(Receipts::class, Receipts::class);
        $first = $this->entityManager->wrapInTransaction(fn (): Receipt => $receipts->issue($this->placedOrder('2026-0001'), new DateTimeImmutable('2026-11-03T16:00:00+00:00')));
        $second = $this->entityManager->wrapInTransaction(fn (): Receipt => $receipts->issue($this->placedOrder('2026-0002'), new DateTimeImmutable()));
        $note = $this->entityManager->wrapInTransaction(static fn (): Receipt => $receipts->credit($first, new DateTimeImmutable(), [SellableReference::of('product', 'dice')]));
        $this->entityManager->wrapInTransaction(static fn (): Receipt => $receipts->credit($first, new DateTimeImmutable('2026-11-03T17:00:00+00:00'), [SellableReference::of('product', 'pdf')]));

        self::assertSame(['R-00000001', 'R-00000002', 'CN-00000001'], [$first->number, $second->number, $note->number]);

        $this->entityManager->clear();
        $repository = $this->service(ReceiptRepository::class, ReceiptRepository::class);
        $stored = $repository->find('R-00000001');

        self::assertNotNull($stored);
        self::assertNotSame($first, $stored);
        self::assertSame('2026-11-03T16:00:00+00:00', $stored->issuedAt->format(\DATE_ATOM));
        self::assertSame('Le Magasin', $stored->issuer->name);
        self::assertSame(['GST' => '123456789 RT0001'], $stored->issuer->taxNumbers);
        self::assertTrue($stored->total()->equals($first->total()));
        self::assertSame(array_map(static fn (Money $tax): int => $tax->minorUnits, $first->taxes()), array_map(static fn (Money $tax): int => $tax->minorUnits, $stored->taxes()));
        self::assertSame('R-00000001', $repository->receiptOf('2026-0001')?->number);
        self::assertNull($repository->receiptOf('2026-0009'));
        self::assertSame(['CN-00000002', 'CN-00000001'], array_map(static fn (Receipt $credit): string => $credit->number, $repository->creditNotesOf('R-00000001')), 'The credit notes come in the order they were issued.');
        self::assertSame([], $repository->creditNotesOf('R-00000002'));
    }

    /**
     * A rolled-back receipt gives its number back: the next one takes it.
     */
    public function testARolledBackReceiptLeavesNoGap(): void
    {
        $receipts = $this->service(Receipts::class, Receipts::class);
        $order = $this->placedOrder('2026-0003');

        try {
            $this->entityManager->wrapInTransaction(static function () use ($receipts, $order): never {
                $receipts->issue($order, new DateTimeImmutable());

                throw new RuntimeException('The printer jammed.');
            });
        } catch (RuntimeException) {
        }

        // A rolled-back transaction closes the entity manager, as the next request of an application would not see.
        $this->service('doctrine', ManagerRegistry::class)->resetManager();
        $order = $this->placedOrder('2026-0004');

        self::assertSame('R-00000001', $this->entityManager->wrapInTransaction(static fn (): string => $receipts->issue($order, new DateTimeImmutable())->number));
    }

    /**
     * Another process took a number since this entity manager last did: the counter is read again under the lock,
     * never taken from the stale copy the identity map keeps.
     */
    public function testALongLivedEntityManagerReadsTheCounterAgain(): void
    {
        $receipts = $this->service(Receipts::class, Receipts::class);
        $first = $this->entityManager->wrapInTransaction(fn (): string => $receipts->issue($this->placedOrder('2026-0006'), new DateTimeImmutable())->number);

        $this->entityManager->getConnection()->executeStatement("UPDATE phil_commerce_document_sequence SET last_number = last_number + 1 WHERE series = 'receipt'");

        $second = $this->entityManager->wrapInTransaction(fn (): string => $receipts->issue($this->placedOrder('2026-0007'), new DateTimeImmutable())->number);

        self::assertSame(['R-00000001', 'R-00000003'], [$first, $second]);
    }

    /**
     * A series whose row a migration created goes on from it.
     */
    public function testASeriesGoesOnFromAnExistingRow(): void
    {
        $this->entityManager->getConnection()->executeStatement("INSERT INTO phil_commerce_document_sequence (series, last_number) VALUES ('receipt', 41)");
        $receipts = $this->service(Receipts::class, Receipts::class);

        self::assertSame('R-00000042', $this->entityManager->wrapInTransaction(fn (): string => $receipts->issue($this->placedOrder('2026-0008'), new DateTimeImmutable())->number));
    }

    public function testANumberIsTakenOnlyInsideATransaction(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A document number is taken inside a transaction');

        $this->service(Receipts::class, Receipts::class)->issue($this->placedOrder('2026-0005'), new DateTimeImmutable());
    }

    private function placedOrder(string $number): Order
    {
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open($number, Currency::of('CAD'));
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->add($order, SellableReference::of('product', 'pdf'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->changeTaxZone($order, 'QC');
        $modifier->place($order, new DateTimeImmutable());

        return $order;
    }
}
