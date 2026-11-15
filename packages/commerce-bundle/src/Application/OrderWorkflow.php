<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Phil\CommerceBundle\Domain\Order\OrderState;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The life cycle of an order, described by `symfony/workflow`: its places are the order's states, its transitions
 * the moves the order allows. The order's own methods make each move, under their invariants, through
 * `OrderMarkingStore`; the workflow checks the move against its graph and its guards, and dispatches the events of
 * `symfony/workflow` under the name `phil_commerce_order`, so that whatever listens to workflows — an automation, an
 * audit — follows orders without knowing the bundle.
 *
 * | Transition | From | To | Does |
 * |---|---|---|---|
 * | `quote` | `cart` | `quoted` | `Order::quote()` |
 * | `place` | `cart`, `quoted` | `placed` | `Order::place()` |
 * | `reopen` | `quoted`, `placed` | `cart` | `Order::reopen()` |
 * | `cancel` | `cart`, `quoted`, `placed` | `cancelled` | `Order::cancel()` |
 * | `expire` | `placed` | `cancelled` | `Order::expire()` |
 */
final class OrderWorkflow
{
    public const string NAME = 'phil_commerce_order';

    public const string SERVICE = 'phil_commerce.order_workflow';

    public const string QUOTE = 'quote';

    public const string PLACE = 'place';

    public const string REOPEN = 'reopen';

    public const string CANCEL = 'cancel';

    public const string EXPIRE = 'expire';

    private function __construct()
    {
    }

    public static function create(OrderMarkingStore $places, ?EventDispatcherInterface $dispatcher = null): StateMachine
    {
        return new StateMachine(self::definition(), $places, $dispatcher, self::NAME);
    }

    public static function definition(): Definition
    {
        $cart = OrderState::Cart->value;
        $quoted = OrderState::Quoted->value;
        $placed = OrderState::Placed->value;
        $cancelled = OrderState::Cancelled->value;

        return new Definition(
            [$cart, $quoted, $placed, $cancelled],
            [
                new Transition(self::QUOTE, $cart, $quoted),
                new Transition(self::PLACE, $cart, $placed),
                new Transition(self::PLACE, $quoted, $placed),
                new Transition(self::REOPEN, $quoted, $cart),
                new Transition(self::REOPEN, $placed, $cart),
                new Transition(self::CANCEL, $cart, $cancelled),
                new Transition(self::CANCEL, $quoted, $cancelled),
                new Transition(self::CANCEL, $placed, $cancelled),
                new Transition(self::EXPIRE, $placed, $cancelled),
            ],
            $cart,
        );
    }
}
