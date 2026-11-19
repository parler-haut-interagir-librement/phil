<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceStoredValueBundle\Application\GiftCards;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\CommerceStoredValueBundle\Application\StoredValuePayments;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle;
use Phil\CommerceStoredValueBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PhilCommerceStoredValueBundle::class)]
final class StoredValueFlowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testAnOrderIsPaidWithAGiftCardAndStoreCredit(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $issued = $this->service(GiftCards::class, GiftCards::class)->issue(Money::of(3_000, Currency::of('CAD')), $at, null, 'A-0');
        $this->service(StoreCredits::class, StoreCredits::class)->grant('ada', Money::of(2_500, Currency::of('CAD')), $at, 'Return');
        $order = Order::open('A-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'BOOK'), 'Book', Money::of(5_000, Currency::of('CAD')), 'standard'), 1, Money::of(5_000, Currency::of('CAD')));
        $order->place($at);
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $order = $this->service(OrderRepository::class, OrderRepository::class)->find('A-1');
        self::assertNotNull($order);
        $stored = $this->service(StoredValuePayments::class, StoredValuePayments::class);
        $card = $stored->payWithGiftCard($order, $issued->code->toString(), $at);
        // Each payment is its own transaction: the core reads what is left to pay from the stored payments.
        $this->entityManager->flush();
        $stored->payWithStoreCredit($order, 'ada', $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = $this->service(InstrumentRepository::class, InstrumentRepository::class);
        self::assertSame(0, $repository->findByCodeHash($issued->code->hash())?->balance->minorUnits);
        self::assertSame(500, $this->service(StoreCredits::class, StoreCredits::class)->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertSame(1, $repository->issuedFor('A-0'));
        self::assertSame([$issued->card->id], array_map(static fn (Instrument $card): string => $card->id, $repository->cardsIssuedFor('A-0')));
        self::assertSame([], $repository->cardsIssuedFor('A-1'));
        self::assertSame([MovementKind::Issued, MovementKind::Redeemed], array_map(static fn (Movement $movement): MovementKind => $movement->kind, $repository->movementsOf($issued->card->id)));
        self::assertSame(-3_000, $repository->redemptionOf($card->id)?->amount->minorUnits);

        $order = $this->service(OrderRepository::class, OrderRepository::class)->find('A-1');
        self::assertNotNull($order);
        $payments = $this->service(Payments::class, Payments::class);
        self::assertTrue($payments->summary($order)->payable()->isZero());

        $payment = $payments->summary($order)->payments[0];
        self::assertSame(PaymentState::Completed, $payment->state());
        $payments->refund($payment, Money::of(1_000, Currency::of('CAD')), 'refund-1');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $refunded = $this->service(InstrumentRepository::class, InstrumentRepository::class)->findInstrument($issued->card->id);
        self::assertSame(1_000, $refunded?->balance->minorUnits);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
