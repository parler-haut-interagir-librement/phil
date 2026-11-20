<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\CommerceReturnsBundle\Application\ReturnCreditNotes;
use Phil\CommerceReturnsBundle\Application\Returns;
use Phil\CommerceReturnsBundle\Application\ReturnSlips;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnCreditNote;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\Resolution;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnSlip;
use Phil\CommerceReturnsBundle\Infrastructure\Doctrine\DoctrineReturnRepository;
use Phil\CommerceReturnsBundle\PhilCommerceReturnsBundle;
use Phil\CommerceReturnsBundle\Tests\Functional\App\TestKernel;
use Phil\CommerceReturnsBundle\Tests\Functional\App\ToldReturns;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PhilCommerceReturnsBundle::class)]
#[CoversClass(DoctrineReturnRepository::class)]
final class ReturnFlowTest extends KernelTestCase
{
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
    }

    public function testAReturnBecomesStoreCredit(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $order = Order::open('A-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'TEA'), 'Tea', Money::of(500, Currency::of('CAD')), 'standard'), 3, Money::of(500, Currency::of('CAD')));
        $order->place($at);
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $returns = $this->service(Returns::class, Returns::class);

        $return = $returns->request($order, [['line' => 'product:TEA', 'quantity' => 2, 'reason' => ReturnReason::ChangedMind]], $at);
        $entityManager->flush();
        $entityManager->clear();

        $returns->authorize($return->id, $at);
        $returns->receive($return->id, ['product:TEA' => ItemCondition::Resellable], $at);

        $settlement = $returns->credit($return->id, 'ada', $at);
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(1_000, $settlement->credited->minorUnits);
        self::assertSame(1_000, $this->service(StoreCredits::class, StoreCredits::class)->balance('ada', Currency::of('CAD'))->minorUnits);
        $stored = $this->service(ReturnRepository::class, ReturnRepository::class)->returnsOf('A-1');
        self::assertCount(1, $stored);
        self::assertSame([Resolution::StoreCredit, ItemCondition::Resellable], [$stored[0]->resolution(), $stored[0]->lines()[0]->condition]);

        $other = Order::open('B-1', Currency::of('CAD'));
        $other->add(Sellable::of(SellableReference::of('product', 'TEA'), 'Tea', Money::of(500, Currency::of('CAD')), 'standard'), 1, Money::of(500, Currency::of('CAD')));
        $other->place($at);
        $this->service(OrderRepository::class, OrderRepository::class)->add($other);
        $returns->request($other, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::ChangedMind]], $at);
        $earlier = $returns->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Damaged]], $at->modify('-1 hour'));
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame([$earlier->id, $return->id], array_map(static fn (ReturnRequest $stored): string => $stored->id, $this->service(ReturnRepository::class, ReturnRepository::class)->returnsOf('A-1')), 'The returns of one order, earliest asked first.');
    }

    /**
     * Through the modules registered beside it: a part of a catalogue's set refused, a special of the promotions
     * taken back, the delivery given back, a partial credit note numbered in the core's series, a slip numbered in
     * its own, an expiry found in SQL, and each step told through the event dispatcher.
     */
    public function testTheModulesPlugInAndEverythingIsStored(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $cad = Currency::of('CAD');
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $products = $this->service(ProductRepository::class, ProductRepository::class);
        $box = Product::create('BOX', ProductKind::Physical, 'Box', Money::of(5_000, $cad), 'goods');
        $box->composeOf([ProductComponent::of('RULES')]);

        $products->add($box);
        $this->service(PromotionRepository::class, PromotionRepository::class)->add(Promotion::bundlePrice('duo', 'Duo', [BundleGroup::of(['BOX'], 1), BundleGroup::of(['DICE'], 1)], Money::of(5_000, $cad)));
        $entityManager->flush();

        $order = Order::open('C-1', $cad);
        $boxLine = SellableReference::of('product', 'BOX');
        $diceLine = SellableReference::of('product', 'DICE');
        $order->add(Sellable::of($boxLine, 'Box', Money::of(5_000, $cad), 'standard'), 1, Money::of(5_000, $cad));
        $order->add(Sellable::of($diceLine, 'Dice', Money::of(1_000, $cad), 'standard'), 1, Money::of(1_000, $cad));
        $order->replaceAdjustments(AdjustmentType::Promotion, [
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-800, $cad), $boxLine, origin: 'promotion:duo'),
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-200, $cad), $diceLine, origin: 'promotion:duo'),
        ]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(900, $cad))]);
        $order->place($at);
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $this->service(ReceiptRepository::class, ReceiptRepository::class)->add(Receipt::issue('R-1', $order, Issuer::fromArray(['name' => 'Shop', 'address' => ['1 Main St'], 'tax_numbers' => []]), $at));
        $entityManager->flush();

        $returns = $this->service(Returns::class, Returns::class);

        try {
            $returns->request($order, [['line' => 'product:RULES', 'quantity' => 1, 'reason' => ReturnReason::Defective]], $at);
            self::fail('A part of a set came back alone.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('In the order C-1, product:RULES came in the set product:BOX: the set comes back whole, never a part of it.', $returnRefused->getMessage());
        }

        $return = $returns->request($order, [['line' => 'product:DICE', 'quantity' => 1, 'reason' => ReturnReason::Defective]], $at);
        $returns->authorize($return->id, $at);
        $slip = $entityManager->wrapInTransaction(fn (): ReturnSlip => $this->service(ReturnSlips::class, ReturnSlips::class)->slipFor($return->id, $at));
        $returns->receive($return->id, ['product:DICE' => ItemCondition::Resellable], $at);
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame('RS-00000001', $slip->number);
        $stored = $this->service(ReturnRepository::class, ReturnRepository::class)->findReturn($return->id);
        self::assertNotNull($stored);
        self::assertSame([['promotion:duo' => 1_000], 900, 'RS-00000001'], [$stored->specialCharges(), $stored->deliveryRefund()->minorUnits, $stored->slipNumber()]);
        self::assertSame(1_000 - 1_000 + 900, $stored->value()->minorUnits);

        $note = $entityManager->wrapInTransaction(fn (): ReturnCreditNote => $this->service(ReturnCreditNotes::class, ReturnCreditNotes::class)->issue($return->id, $at));
        $entityManager->clear();
        $read = $this->service(ReturnRepository::class, ReturnRepository::class)->findCreditNote($return->id);
        self::assertNotNull($read);
        self::assertSame([$note->number, 'R-1', 900, 1_000, 900], [$read->number, $read->receiptNumber, $read->total()->minorUnits, $read->specialDiscount()->minorUnits, $read->delivery()->minorUnits]);
        self::assertSame([['product:DICE', 'Dice', 1, 1_000]], array_map(static fn (array $line): array => [$line['line'], $line['description'], $line['quantity'], $line['amount']->minorUnits], $read->lines()));
        self::assertNull($this->service(ReturnRepository::class, ReturnRepository::class)->findCreditNote('nope'));

        $waiting = $returns->request($this->service(OrderRepository::class, OrderRepository::class)->find('C-1') ?? $order, [['line' => 'product:BOX', 'quantity' => 1, 'reason' => ReturnReason::ChangedMind]], $at);
        $returns->authorize($waiting->id, $at);
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame([], $returns->expire($at->modify('+14 days')));
        $expired = $returns->expire($at->modify('+14 days +1 second'));
        self::assertSame([$waiting->id], array_map(static fn (ReturnRequest $return): string => $return->id, $expired));
        self::assertSame([ReturnState::Requested, ReturnState::Authorized, ReturnState::Received, ReturnState::Requested, ReturnState::Authorized, ReturnState::Expired], $this->service(ToldReturns::class, ToldReturns::class)->states);
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
