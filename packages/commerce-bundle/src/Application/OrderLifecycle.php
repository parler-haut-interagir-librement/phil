<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Contract\TransitionForcing;
use Phil\CommerceBundle\Domain\Contract\OrderJournal;
use Phil\CommerceBundle\Domain\Event\OrderCancelled;
use Phil\CommerceBundle\Domain\Event\OrderStateForced;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Moves orders along the `phil_commerce_order` workflow, journals each move, forces a move outside the workflow for
 * whoever `TransitionForcing` allows, and sets the application's statuses, each attached to a state.
 *
 * - **A transition goes through the workflow**: its guards may block it, its events are dispatched, and the order's
 *   own method makes the move.
 * - **A forced move is journalled with its reason**, then announced by `OrderStateForced`; nobody may force one until
 *   the application says who may.
 * - **A cancellation is announced once** by `OrderCancelled`, whether the order was cancelled, expired or forced to
 *   `cancelled` — what an activity feed or a loyalty programme listens to.
 * - **A status belongs to one state**, as `phil_commerce.order_statuses` declares; a move of the state clears it.
 *
 * Nothing is committed here: the journal's lines are stored with the application's next commit.
 */
final readonly class OrderLifecycle
{
    /**
     * @param array<string, string> $statuses status → the state it belongs to, as `phil_commerce.order_statuses` holds them
     */
    public function __construct(
        private WorkflowInterface $workflow,
        private TransitionForcing $forcing = new DenyForcing(),
        private ?OrderJournal $journal = null,
        private array $statuses = [],
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function can(Order $order, string $transition): bool
    {
        return $this->workflow->can($order, $transition);
    }

    public function quote(Order $order, DateTimeImmutable $at, ?string $actor = null): void
    {
        $this->apply($order, OrderWorkflow::QUOTE, $at, $actor);
    }

    public function place(Order $order, DateTimeImmutable $at, ?string $actor = null): void
    {
        $this->apply($order, OrderWorkflow::PLACE, $at, $actor);
    }

    public function reopen(Order $order, DateTimeImmutable $at, ?string $actor = null): void
    {
        $this->apply($order, OrderWorkflow::REOPEN, $at, $actor);
    }

    public function cancel(Order $order, DateTimeImmutable $at, ?string $actor = null): void
    {
        $this->apply($order, OrderWorkflow::CANCEL, $at, $actor);
    }

    public function expire(Order $order, DateTimeImmutable $at, ?string $actor = null): void
    {
        $this->apply($order, OrderWorkflow::EXPIRE, $at, $actor);
    }

    /**
     * Takes a transition of the workflow, refused when the graph or a guard does not open it to the order.
     *
     * @param string|null $actor the stable identifier of who moves the order, journalled; `null` for the application itself
     */
    public function apply(Order $order, string $transition, DateTimeImmutable $at, ?string $actor = null): void
    {
        $from = $order->state();

        if (!$this->workflow->can($order, $transition)) {
            throw OrderLocked::blocked($order->number, $from, $transition);
        }

        $this->workflow->apply($order, $transition, ['at' => $at, 'transition' => $transition, 'actor' => $actor]);
        $this->journal?->record(OrderStateChange::transition($order->number, $from, $order->state(), $transition, $actor, $at));
        $this->announceIfCancelled($order, $from, $actor, $at);
    }

    /**
     * Forces the order to a state outside its transitions, for whoever `TransitionForcing` allows, and journals it with
     * its reason before announcing it.
     */
    public function force(Order $order, OrderState $to, string $actor, string $reason, DateTimeImmutable $at): void
    {
        $from = $order->state();

        if (!$this->forcing->allows($order, $to, $actor)) {
            throw OrderLocked::forcingRefused($order->number, $from, $to);
        }

        // The journal's line is checked first: a reason it refuses leaves the order as it was.
        $change = OrderStateChange::forced($order->number, $from, $to, $actor, $reason, $at);
        $order->force($to, $at);
        $this->journal?->record($change);
        $this->dispatcher?->dispatch(new OrderStateForced($order->number, $from, $to, $actor, $at));
        $this->announceIfCancelled($order, $from, $actor, $at);
    }

    /**
     * Sets the application's status of the order, or clears it with `null`; a status the configuration does not
     * declare, or that belongs to another state, is refused.
     */
    public function changeStatus(Order $order, ?string $status): void
    {
        if (null === $status) {
            $order->changeStatus(null, $order->state());

            return;
        }

        $state = $this->statuses[$status] ?? throw InvalidValue::because(\sprintf('"%s" is not a status of phil_commerce.order_statuses.', $status));
        $order->changeStatus($status, OrderState::from($state));
    }

    /**
     * @return array<string, OrderState> every status declared, with the state it belongs to
     */
    public function statuses(): array
    {
        return array_map(OrderState::from(...), $this->statuses);
    }

    private function announceIfCancelled(Order $order, OrderState $from, ?string $actor, DateTimeImmutable $at): void
    {
        if (OrderState::Cancelled !== $order->state()) {
            return;
        }

        $this->dispatcher?->dispatch(new OrderCancelled($order->number, $from, $order->channel(), $order->customer(), $order->expiredAt() instanceof DateTimeImmutable, $actor, $at));
    }
}
