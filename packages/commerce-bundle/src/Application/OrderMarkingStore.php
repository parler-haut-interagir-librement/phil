<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

/**
 * The place of an order is its state, and storing a place is the order's own move: `Order::place()`, `quote()`,
 * `reopen()`, `cancel()` or `expire()`, each under its invariants. The workflow's context carries the moment of the
 * move under `at` and the transition under `transition`, which tells an expiry from a cancellation.
 */
final readonly class OrderMarkingStore implements MarkingStoreInterface
{
    #[Override]
    public function getMarking(object $subject): Marking
    {
        return new Marking([$this->order($subject)->state()->value => 1]);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $order = $this->order($subject);
        $at = $context['at'] ?? null;

        if (!$at instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('An order moves at a moment: the workflow context holds it under "at".');
        }

        foreach (array_keys($marking->getPlaces()) as $place) {
            match (OrderState::from((string) $place)) {
                OrderState::Quoted => $order->quote(),
                OrderState::Placed => $order->place($at),
                OrderState::Cart => $order->reopen(),
                OrderState::Cancelled => OrderWorkflow::EXPIRE === ($context['transition'] ?? null) ? $order->expire($at) : $order->cancel($at),
            };
        }
    }

    private function order(object $subject): Order
    {
        if (!$subject instanceof Order) {
            throw new InvalidArgumentException(\sprintf('The order workflow moves a %s, not a %s.', Order::class, $subject::class));
        }

        return $subject;
    }
}
