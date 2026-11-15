<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use Phil\CommerceBundle\Application\Contract\TransitionForcing;
use Phil\CommerceBundle\Application\DenyForcing;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Application\OrderMarkingStore;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Domain\Event\OrderCancelled;
use Phil\CommerceBundle\Domain\Event\OrderStateForced;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryCommerceStore;
use Phil\CommerceBundle\Tests\Unit\Support\RecordingDispatcher;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

#[CoversClass(OrderLifecycle::class)]
#[CoversClass(OrderWorkflow::class)]
#[CoversClass(OrderMarkingStore::class)]
#[CoversClass(DenyForcing::class)]
#[CoversClass(OrderStateForced::class)]
#[CoversClass(OrderCancelled::class)]
final class OrderLifecycleTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private InMemoryCommerceStore $journal;

    /** @var list<string> */
    private array $heard = [];

    #[Override]
    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->journal = new InMemoryCommerceStore();

        foreach (['workflow.phil_commerce_order.leave', 'workflow.phil_commerce_order.entered', 'workflow.phil_commerce_order.completed.place'] as $name) {
            $this->dispatcher->addListener($name, function () use ($name): void {
                $this->heard[] = $name;
            });
        }
    }

    /**
     * The workflow's graph and the order's own moves allow exactly the same transitions.
     */
    public function testTheWorkflowDescribesTheMovesTheOrderAllows(): void
    {
        $definition = OrderWorkflow::definition();
        self::assertEquals([
            new Transition('quote', 'cart', 'quoted'), new Transition('place', 'cart', 'placed'), new Transition('place', 'quoted', 'placed'),
            new Transition('reopen', 'quoted', 'cart'), new Transition('reopen', 'placed', 'cart'), new Transition('cancel', 'cart', 'cancelled'),
            new Transition('cancel', 'quoted', 'cancelled'), new Transition('cancel', 'placed', 'cancelled'), new Transition('expire', 'placed', 'cancelled'),
        ], $definition->getTransitions());
        self::assertSame(['cart', 'quoted', 'placed', 'cancelled'], array_values($definition->getPlaces()));
        self::assertSame(['cart'], $definition->getInitialPlaces());

        $moves = [
            OrderWorkflow::QUOTE => static fn (Order $order) => $order->quote(),
            OrderWorkflow::PLACE => static fn (Order $order) => $order->place(new DateTimeImmutable()),
            OrderWorkflow::REOPEN => static fn (Order $order) => $order->reopen(),
            OrderWorkflow::CANCEL => static fn (Order $order) => $order->cancel(new DateTimeImmutable()),
            OrderWorkflow::EXPIRE => static fn (Order $order) => $order->expire(new DateTimeImmutable()),
        ];

        foreach (OrderState::cases() as $from) {
            foreach ($moves as $name => $move) {
                $order = $this->orderIn($from);
                $workflow = OrderWorkflow::create(new OrderMarkingStore());
                $described = $workflow->can($order, $name);

                try {
                    $move($order);
                    $allowed = true;
                } catch (OrderLocked) {
                    $allowed = false;
                }

                self::assertSame($allowed, $described, $from->value.' '.$name);
            }
        }
    }

    public function testEachTransitionMovesTheOrderAndIsJournalled(): void
    {
        $lifecycle = $this->lifecycle();
        $order = $this->orderIn(OrderState::Cart);
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $lifecycle->quote($order, $at, 'clerk-1');
        self::assertSame(OrderState::Quoted, $order->state());
        $lifecycle->reopen($order, $at);
        self::assertSame(OrderState::Cart, $order->state());
        $lifecycle->place($order, $at, 'clerk-1');
        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame($at, $order->placedAt());
        $lifecycle->expire($order, $at);
        self::assertSame($at, $order->expiredAt());

        $moves = array_map(static fn (OrderStateChange $move): string => $move->from->value.'>'.$move->to->value.' '.$move->transition.' '.($move->actor ?? '-'), $this->journal->moves);
        self::assertSame(['cart>quoted quote clerk-1', 'quoted>cart reopen -', 'cart>placed place clerk-1', 'placed>cancelled expire -'], $moves);
        self::assertSame($at, $this->journal->moves[0]->occurredAt);
        self::assertContains('workflow.phil_commerce_order.completed.place', $this->heard);
        self::assertContains('workflow.phil_commerce_order.entered', $this->heard);
    }

    public function testACancellationIsNoExpiry(): void
    {
        $order = $this->orderIn(OrderState::Placed);
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $this->lifecycle()->cancel($order, $at);

        self::assertSame(OrderState::Cancelled, $order->state());
        self::assertSame($at, $order->cancelledAt());
        self::assertNull($order->expiredAt());
    }

    public function testATransitionTheGraphDoesNotOpenIsRefused(): void
    {
        $order = $this->orderIn(OrderState::Cancelled);
        $lifecycle = $this->lifecycle();
        self::assertFalse($lifecycle->can($order, OrderWorkflow::PLACE));

        try {
            $lifecycle->place($order, new DateTimeImmutable());
            self::fail('A cancelled order is not placed.');
        } catch (OrderLocked $orderLocked) {
            self::assertSame('The order 2026-0001 is cancelled: the transition "place" is not open to it.', $orderLocked->getMessage());
        }

        self::assertSame([], $this->journal->moves);
    }

    public function testAGuardBlocksATransition(): void
    {
        $this->dispatcher->addListener('workflow.phil_commerce_order.guard.place', static fn (GuardEvent $event) => $event->setBlocked(true, 'Not today.'));
        $order = $this->orderIn(OrderState::Cart);

        $this->expectException(OrderLocked::class);

        $this->lifecycle()->place($order, new DateTimeImmutable());
    }

    public function testNobodyForcesAnOrderByDefault(): void
    {
        $order = $this->orderIn(OrderState::Cancelled);
        $recorded = new RecordingDispatcher();
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), journal: $this->journal, dispatcher: $recorded);

        try {
            $lifecycle->force($order, OrderState::Placed, 'manager-1', 'Mistake', new DateTimeImmutable());
            self::fail('Nobody forces an order.');
        } catch (OrderLocked $orderLocked) {
            self::assertSame('The order 2026-0001 is cancelled: nobody here may force it to placed.', $orderLocked->getMessage());
        }

        self::assertSame(OrderState::Cancelled, $order->state());
        self::assertSame([], $this->journal->moves);
        self::assertSame([], $recorded->heard());
    }

    public function testAForcedMoveIsJournalledWithItsReasonThenAnnounced(): void
    {
        $order = $this->orderIn(OrderState::Cancelled);
        $recorded = new RecordingDispatcher();
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'), $this->journal, [], $recorded);

        $lifecycle->force($order, OrderState::Placed, 'manager-1', 'Cancelled by mistake', $at);

        self::assertSame(OrderState::Placed, $order->state());
        self::assertCount(1, $this->journal->moves);
        self::assertTrue($this->journal->moves[0]->wasForced());
        self::assertSame('Cancelled by mistake', $this->journal->moves[0]->reason);
        self::assertEquals([new OrderStateForced('2026-0001', OrderState::Cancelled, OrderState::Placed, 'manager-1', $at)], $recorded->events);
        self::assertSame($at, $recorded->events[0] instanceof OrderStateForced ? $recorded->events[0]->occurredAt() : null);
    }

    public function testAForcedMoveWithoutAReasonLeavesTheOrderAsItWas(): void
    {
        $order = $this->orderIn(OrderState::Cancelled);
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'), $this->journal);

        try {
            $lifecycle->force($order, OrderState::Placed, 'manager-1', ' ', new DateTimeImmutable());
            self::fail('A forced move has a reason.');
        } catch (InvalidValue) {
        }

        self::assertSame(OrderState::Cancelled, $order->state());
        self::assertSame([], $this->journal->moves);
    }

    public function testOnlyTheAllowedActorForces(): void
    {
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'));

        $this->expectException(OrderLocked::class);

        $lifecycle->force($this->orderIn(OrderState::Cancelled), OrderState::Placed, 'clerk-1', 'Mistake', new DateTimeImmutable());
    }

    public function testAStatusIsDeclaredAndAttachedToItsState(): void
    {
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), statuses: ['awaiting_pickup' => 'placed', 'draft' => 'cart']);
        $order = $this->orderIn(OrderState::Placed);

        self::assertSame(['awaiting_pickup' => OrderState::Placed, 'draft' => OrderState::Cart], $lifecycle->statuses());

        $lifecycle->changeStatus($order, 'awaiting_pickup');
        self::assertSame('awaiting_pickup', $order->status());

        $lifecycle->changeStatus($order, null);
        self::assertNull($order->status());

        try {
            $lifecycle->changeStatus($order, 'draft');
            self::fail('A cart status on a placed order.');
        } catch (OrderLocked) {
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"shipped" is not a status of phil_commerce.order_statuses.');

        $lifecycle->changeStatus($order, 'shipped');
    }

    public function testTheMarkingStoreMovesOnlyOrdersAtAMoment(): void
    {
        $store = new OrderMarkingStore();
        $order = $this->orderIn(OrderState::Cart);

        self::assertSame(['cart' => 1], $store->getMarking($order)->getPlaces());

        try {
            $store->setMarking($order, new Marking(['placed' => 1]));
            self::fail('A move without its moment.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('An order moves at a moment: the workflow context holds it under "at".', $invalidArgumentException->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The order workflow moves a Phil\CommerceBundle\Domain\Model\Order, not a stdClass.');

        $store->getMarking(new stdClass());
    }

    private function lifecycle(): OrderLifecycle
    {
        return new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore(), $this->dispatcher), new DenyForcing(), $this->journal);
    }

    private function allowing(string $actor): TransitionForcing
    {
        return new readonly class($actor) implements TransitionForcing {
            public function __construct(private string $actor)
            {
            }

            #[Override]
            public function allows(Order $order, OrderState $to, string $actor): bool
            {
                return $actor === $this->actor;
            }
        };
    }

    private function orderIn(OrderState $state): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $price, 'goods'), 1, $price);

        match ($state) {
            OrderState::Cart => null,
            OrderState::Quoted => $order->quote(),
            OrderState::Placed => $order->place(new DateTimeImmutable()),
            OrderState::Cancelled => $order->cancel(new DateTimeImmutable()),
        };

        return $order;
    }

    public function testAnyTransitionOfTheWorkflowIsTakenByName(): void
    {
        $order = $this->orderIn(OrderState::Cart);

        new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), journal: $this->journal)->apply($order, OrderWorkflow::PLACE, new DateTimeImmutable('2026-11-11T10:00:00Z'), 'clerk-1');

        self::assertSame(OrderState::Placed, $order->state());
        self::assertCount(1, $this->journal->moves);
    }

    public function testWithoutAJournalOrAListenerTheOrderMovesAllTheSame(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'));
        $order = $this->orderIn(OrderState::Cart);

        $lifecycle->place($order, $at);
        self::assertSame(OrderState::Placed, $order->state());

        $lifecycle->force($order, OrderState::Cancelled, 'manager-1', 'Mistake', $at);
        self::assertSame(OrderState::Cancelled, $order->state());
    }

    public function testAForcedMoveIsAnnouncedWithoutAJournalAndJournalledWithoutAListener(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $recorded = new RecordingDispatcher();
        $announcing = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'), dispatcher: $recorded);
        $announcing->force($this->orderIn(OrderState::Cancelled), OrderState::Placed, 'manager-1', 'Mistake', $at);
        self::assertCount(1, $recorded->events);

        $journalling = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'), $this->journal);
        $journalling->force($this->orderIn(OrderState::Cancelled), OrderState::Placed, 'manager-1', 'Mistake', $at);
        self::assertCount(1, $this->journal->moves);
    }

    public function testACancellationIsAnnouncedWithTheIdentifiersOfTheOrder(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $recorded = new RecordingDispatcher();
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), dispatcher: $recorded);
        $order = $this->memberOrder();

        $lifecycle->place($order, $at, 'member-7');
        self::assertSame([], $recorded->heard());

        $lifecycle->cancel($order, $at, 'clerk-1');

        self::assertEquals([new OrderCancelled('2026-0001', OrderState::Placed, 'web', 'member-7', false, 'clerk-1', $at)], $recorded->events);
        self::assertSame($at, $recorded->events[0] instanceof OrderCancelled ? $recorded->events[0]->occurredAt() : null);
    }

    public function testAnExpiryIsAnnouncedAsExpired(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $recorded = new RecordingDispatcher();
        $order = $this->memberOrder();
        $order->place($at);

        new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), dispatcher: $recorded)->expire($order, $at);

        self::assertEquals([new OrderCancelled('2026-0001', OrderState::Placed, 'web', 'member-7', true, null, $at)], $recorded->events);
    }

    public function testACartGivenUpIsAnnouncedFromTheCart(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $recorded = new RecordingDispatcher();

        new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), dispatcher: $recorded)->cancel($this->orderIn(OrderState::Cart), $at);

        self::assertEquals([new OrderCancelled('2026-0001', OrderState::Cart, null, null, false, null, $at)], $recorded->events);
    }

    public function testAForcedCancellationIsAnnouncedAfterTheForcedMove(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $recorded = new RecordingDispatcher();
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'), dispatcher: $recorded);
        $order = $this->memberOrder();
        $order->place($at);

        $lifecycle->force($order, OrderState::Cancelled, 'manager-1', 'Fraud', $at);

        self::assertEquals([
            new OrderStateForced('2026-0001', OrderState::Placed, OrderState::Cancelled, 'manager-1', $at),
            new OrderCancelled('2026-0001', OrderState::Placed, 'web', 'member-7', false, 'manager-1', $at),
        ], $recorded->events);
    }

    public function testAForcedMoveOutOfCancelledIsNoCancellation(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $recorded = new RecordingDispatcher();
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), $this->allowing('manager-1'), dispatcher: $recorded);

        $lifecycle->force($this->orderIn(OrderState::Cancelled), OrderState::Placed, 'manager-1', 'Mistake', $at);

        self::assertCount(1, $recorded->events);
        self::assertInstanceOf(OrderStateForced::class, $recorded->events[0]);
    }

    public function testACancellationWithoutADispatcherStillCancels(): void
    {
        $order = $this->memberOrder();

        new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()))->cancel($order, new DateTimeImmutable('2026-11-11T10:00:00Z'));

        self::assertSame(OrderState::Cancelled, $order->state());
    }

    private function memberOrder(): Order
    {
        $order = $this->orderIn(OrderState::Cart);
        $order->changeChannel('web');
        $order->assignCustomer('member-7');

        return $order;
    }
}
