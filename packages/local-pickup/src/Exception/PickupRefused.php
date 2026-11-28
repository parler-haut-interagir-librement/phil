<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Exception;

use Phil\LocalPickup\PickupState;
use Phil\Reservation\Violation;
use RuntimeException;

/**
 * A pickup that cannot be placed, made ready or handed over as asked. The reason is a stable key an application
 * translates; a slot refused also carries the violations phil/reservation found.
 */
final class PickupRefused extends RuntimeException implements LocalPickupException
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly array $violations = [],
    ) {
        parent::__construct($message);
    }

    public static function pointClosed(string $point): self
    {
        return new self(\sprintf('The pickup point "%s" takes no new order.', $point), 'point_closed');
    }

    public static function alreadyPlaced(string $order): self
    {
        return new self(\sprintf('The order "%s" already has a pickup.', $order), 'already_placed');
    }

    /**
     * @param list<Violation> $violations
     */
    public static function slot(string $order, array $violations): self
    {
        return new self(\sprintf('The slot asked for the order "%s" cannot be booked.', $order), 'slot', $violations);
    }

    public static function state(string $order, PickupState $state, string $action): self
    {
        return new self(\sprintf('The pickup of the order "%s" is %s: it cannot be %s.', $order, $state->value, $action), 'state_'.$state->value);
    }

    public static function noCode(string $order): self
    {
        return new self(\sprintf('The pickup of the order "%s" has no code yet: its notice is not sent.', $order), 'no_code');
    }

    public static function holdRunning(string $order): self
    {
        return new self(\sprintf('The pickup of the order "%s" is still within its hold period.', $order), 'hold_running');
    }
}
