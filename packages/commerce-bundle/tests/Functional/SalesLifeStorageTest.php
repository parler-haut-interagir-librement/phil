<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use LogicException;
use Override;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Application\GiftWrapping;
use Phil\CommerceBundle\Application\OrderChanges;
use Phil\CommerceBundle\Application\OrderExpiry;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Application\PaymentLinks;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Application\WishLists;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Contract\OrderJournal;
use Phil\CommerceBundle\Domain\Contract\WishListRepository;
use Phil\CommerceBundle\Domain\Model\Channel;
use Phil\CommerceBundle\Domain\Model\DeferredSale;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderChange;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\Refund;
use Phil\CommerceBundle\Domain\Model\WishList;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderChangeRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderJournal;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLinkRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLock;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineRefundRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineWishListRepository;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The life of a sale through the container and an SQLite database: gifts, manual discounts, the workflow and its
 * journal, wish lists bought from, offline payments, payment links, changes, refunds, the register, and the expiry
 * of the orders nobody paid.
 */
#[CoversClass(PhilCommerceBundle::class)]
#[CoversClass(DoctrineOrderRepository::class)]
#[CoversClass(DoctrineWishListRepository::class)]
#[CoversClass(DoctrinePaymentLinkRepository::class)]
#[CoversClass(DoctrineOrderChangeRepository::class)]
#[CoversClass(DoctrineOrderJournal::class)]
#[CoversClass(DoctrinePaymentLock::class)]
#[CoversClass(DoctrineRefundRepository::class)]
#[CoversClass(DoctrineDocumentNumbering::class)]
final class SalesLifeStorageTest extends FunctionalTestCase
{
    private const array CONFIG = TestKernel::RATES + [
        'gift_wrapping' => ['enabled' => true, 'prices' => ['CAD' => '4.00'], 'recycled_prices' => ['CAD' => '3.00']],
        'offline_payments' => ['cheque'],
        'order_statuses' => ['awaiting_pickup' => 'placed'],
        'document_types' => ['packing_slip' => ['prefix' => 'PS-', 'digits' => 4]],
        'payment_links' => ['default_hours' => 48],
        'order_expiry' => ['unpaid_hours' => 12, 'delete_after_days' => 10],
    ];

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $this->createSchema($this->entityManager);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, self::CONFIG);
    }

    public function testAGiftWithADiscountIsPlacedThroughTheWorkflowAndJournalled(): void
    {
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $lifecycle = $this->service(OrderLifecycle::class, OrderLifecycle::class);
        $order = $this->cart('2026-0001');

        $modifier->offerAsGift($order, GiftOptions::of(true, true, 'Enjoy', true));
        $modifier->grantManualDiscount($order, ManualDiscount::share('loyal', 1_000, 'Regular', 'clerk-1'));
        $modifier->place($order, new DateTimeImmutable('2026-11-11T10:00:00Z'), 'clerk-1');

        $lifecycle->changeStatus($order, 'awaiting_pickup');
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $stored = $this->entityManager->find(Order::class, '2026-0001');
        self::assertInstanceOf(Order::class, $stored);
        self::assertSame(OrderState::Placed, $stored->state());
        self::assertSame('awaiting_pickup', $stored->status());
        self::assertEquals(GiftOptions::of(true, true, 'Enjoy', true), $stored->giftOptions());
        self::assertSame('loyal', $stored->manualDiscounts()[0]->key);
        self::assertSame('customer-1', $stored->customer());
        // 19.99 less 10 %, 300 of recycled wrap.
        self::assertSame(1_999 - 199 + 300, $stored->itemsTotal()->add($stored->adjustmentsTotal())->subtract($stored->adjustmentsTotal(AdjustmentType::Tax))->minorUnits);

        $moves = $this->service(OrderJournal::class, OrderJournal::class)->movesOf('2026-0001');
        self::assertCount(1, $moves);
        self::assertSame('place', $moves[0]->transition);
        self::assertTrue(self::getContainer()->has(OrderWorkflow::SERVICE));
        self::assertInstanceOf(GiftWrapping::class, self::getContainer()->get(GiftWrapping::class));
    }

    public function testAnOfflinePaymentOfAnOrderFromAWishListCountsTowardsTheList(): void
    {
        $lists = $this->service(WishLists::class, WishLists::class);
        $payments = $this->service(Payments::class, Payments::class);
        $list = $lists->create('member-1', 'Birthday', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');
        $list->wish($dice, 2);
        $token = $lists->share($list);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $shared = $lists->shared($token);
        self::assertInstanceOf(WishList::class, $shared);
        $order = Order::open('2026-0002', Currency::of('CAD'));
        $lists->buyFrom($shared, $order, $dice, 1, PricingContext::of(1, new DateTimeImmutable()));
        $order->place(new DateTimeImmutable());
        $this->entityManager->persist($order);
        $payment = $payments->declareOffline($order, 'cheque', new DateTimeImmutable());
        $this->entityManager->flush();

        $payments->confirmOffline($order, $payment, new DateTimeImmutable());
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->service(WishListRepository::class, WishListRepository::class)->findWishList($list->id);
        self::assertSame(1, $read?->item($dice)?->bought);
        self::assertCount(1, $this->service(WishListRepository::class, WishListRepository::class)->wishListsOf('member-1'));
        self::assertSame(PaymentState::Completed, $this->entityManager->find(Payment::class, $payment->id)?->state());
    }

    public function testLinksChangesRefundsAndTheRegisterAreStored(): void
    {
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = $this->cart('2026-0003');
        $modifier->place($order, new DateTimeImmutable());
        $this->entityManager->persist($order);
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $links = $this->service(PaymentLinks::class, PaymentLinks::class);
        [$link, $token] = $links->create($order, $at);
        self::assertEquals(new DateTimeImmutable('2026-11-13T10:00:00Z'), $link->expiresAt);

        $changes = $this->service(OrderChanges::class, OrderChanges::class);
        $change = $changes->prepare($order, [OrderChangeLine::of(SellableReference::of('product', 'dice'), 2)], 'Second set', 'clerk-1', PricingContext::of(1, $at), $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $order = $this->entityManager->find(Order::class, '2026-0003');
        self::assertInstanceOf(Order::class, $order);
        self::assertSame($order->number, $links->open($token, $at)?->number);
        $change = $this->entityManager->find(OrderChange::class, $change->id);
        self::assertInstanceOf(OrderChange::class, $change);
        $changes->confirm($change, $order, 'manager-1', PricingContext::of(1, $at), $at);
        self::assertSame(1, $links->revokeAll($order, $at));
        $this->entityManager->flush();
        self::assertSame(2, $order->revision());

        $payments = $this->service(Payments::class, Payments::class);
        $payment = $payments->declareOffline($order, 'cheque', $at);
        $payments->confirmOffline($order, $payment, $at);
        $refunds = $this->service(Refunds::class, Refunds::class);

        try {
            $refunds->grant($payment, Money::of(500, Currency::of('CAD')), 'Damaged', 'clerk-1', $at);
            self::fail('A refund is granted inside a transaction only: the lock of the payment would end with its statement.');
        } catch (LogicException $logicException) {
            self::assertStringContainsString('wrapInTransaction()', $logicException->getMessage());
        }

        $this->entityManager->flush();
        $refund = $this->entityManager->wrapInTransaction(static fn (): Refund => $refunds->grant($payment, Money::of(500, Currency::of('CAD')), 'Damaged', 'clerk-1', $at));

        // A processor's notification applied elsewhere since the payment was read: it is read again before granting.
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('UPDATE phil_commerce_payment SET version = version + 1 WHERE id = ?', [$payment->id]);
        $connection->beginTransaction();

        try {
            $refunds->grant($payment, Money::of(100, Currency::of('CAD')), 'Damaged', 'clerk-1', $at);
            self::fail('A payment changed since it was read is not refunded from its stale copy.');
        } catch (OptimisticLockException $optimisticLockException) {
            self::assertSame($payment, $optimisticLockException->getEntity());
            self::assertSame(1, preg_match('/version (\d+) was expected, but is actually (\d+)$/D', $optimisticLockException->getMessage(), $versions));
            self::assertSame((int) $versions[1] + 1, (int) $versions[2]);
        } finally {
            $connection->rollBack();
        }

        $this->entityManager->clear();

        self::assertInstanceOf(Refund::class, $this->entityManager->find(Refund::class, $refund->id));

        $number = $this->entityManager->wrapInTransaction(fn (): string => $this->service(DocumentRegister::class, DocumentRegister::class)->next('packing_slip'));
        self::assertSame('PS-0001', $number);
    }

    public function testUnpaidOrdersExpireByTheirChannelAndAreDeletedOnceKeptLongEnough(): void
    {
        $store = Channel::create('store', 'Store', Currency::of('CAD'), 'fr');
        $store->expireUnpaidOrders(24, 30);

        $this->entityManager->persist($store);
        $placedAt = new DateTimeImmutable('2026-11-01T10:00:00Z');

        $unpaid = $this->placed('U-1', 'store', $placedAt);
        $failed = $this->placed('U-2', 'store', $placedAt);
        $this->entityManager->persist(new FailedPayment()->of($failed));
        $paid = $this->placed('P-1', 'store', $placedAt);
        $payments = $this->service(Payments::class, Payments::class);
        $cheque = $payments->declareOffline($paid, 'cheque', $placedAt);
        $payments->confirmOffline($paid, $cheque, $placedAt);
        $deferred = $this->placed('D-1', 'store', $placedAt);
        [$sale] = DeferredSale::open($deferred, DeferredSalePolicy::preorder(Deposit::share(2_000)), $placedAt);
        $this->entityManager->persist($sale);
        $recent = $this->placed('R-1', 'store', new DateTimeImmutable('2026-11-11T09:00:00Z'));
        $elsewhere = $this->placed('E-1', 'fair', new DateTimeImmutable('2026-11-10T20:00:00Z'));
        $this->entityManager->flush();

        $expiry = $this->service(OrderExpiry::class, OrderExpiry::class);
        $sweep = $expiry->sweep(new DateTimeImmutable('2026-11-11T10:00:00Z'));
        $this->entityManager->flush();

        self::assertSame(['U-1', 'U-2', 'E-1'], $sweep->expired);
        self::assertSame([], $sweep->deleted);
        self::assertNotNull($unpaid->expiredAt());
        self::assertSame(OrderState::Placed, $paid->state());
        self::assertSame(OrderState::Placed, $deferred->state());
        self::assertSame(OrderState::Placed, $recent->state());
        self::assertSame(OrderState::Cancelled, $elsewhere->state());

        $later = $expiry->sweep(new DateTimeImmutable('2026-12-11T10:00:01Z'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['R-1'], $later->expired);
        self::assertSame(['U-1', 'U-2', 'E-1'], $later->deleted);
        self::assertNull($this->entityManager->find(Order::class, 'U-1'));
        self::assertNull($this->entityManager->find(Order::class, 'U-2'));
        self::assertNull($this->entityManager->find(Order::class, 'E-1'));
        self::assertSame([], $this->entityManager->getRepository(Payment::class)->findBy(['orderNumber' => 'U-2']));
        self::assertSame([], $this->service(OrderJournal::class, OrderJournal::class)->movesOf('U-1'));
        self::assertNotNull($this->entityManager->find(Order::class, 'P-1'));
        self::assertNotNull($this->entityManager->find(Order::class, 'R-1'));
    }

    public function testAnExpiredOrderPaidLateIsKept(): void
    {
        $order = $this->placed('L-1', null, new DateTimeImmutable('2026-11-01T10:00:00Z'));
        $this->entityManager->flush();
        $expiry = $this->service(OrderExpiry::class, OrderExpiry::class);
        $expiry->sweep(new DateTimeImmutable('2026-11-02T10:00:00Z'));

        $payment = new FailedPayment()->of($order);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
        $this->entityManager->getConnection()->executeStatement("UPDATE phil_commerce_payment SET state = 'completed'");

        $repository = new DoctrineOrderRepository($this->entityManager);
        $repository->deleteExpired('L-1');
        self::assertSame([], $repository->expiredBefore(null, [], new DateTimeImmutable('2027-01-01T00:00:00Z'), 10));
        $this->entityManager->clear();

        self::assertNotNull($this->entityManager->find(Order::class, 'L-1'));
    }

    private function cart(string $number): Order
    {
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open($number, Currency::of('CAD'));
        $order->assignCustomer('customer-1');

        $modifier->add($order, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, new DateTimeImmutable()));

        return $order;
    }

    private function placed(string $number, ?string $channel, DateTimeImmutable $at): Order
    {
        $order = $this->cart($number);
        $order->changeChannel($channel);
        $order->place($at);

        $this->entityManager->persist($order);

        return $order;
    }
}

/**
 * A payment the processor refused, for an order that stays unpaid.
 */
final class FailedPayment
{
    public function of(Order $order): Payment
    {
        $payment = Payment::create($order, 'card', $order->total(), new DateTimeImmutable());
        $payment->abandon();

        return $payment;
    }
}
