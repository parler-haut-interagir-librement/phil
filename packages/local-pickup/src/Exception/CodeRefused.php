<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Exception;

use RuntimeException;

/**
 * The code given at the counter is not the order's code. The attempt is counted.
 */
final class CodeRefused extends RuntimeException implements LocalPickupException
{
    public function __construct(string $message, public readonly int $attemptsLeft)
    {
        parent::__construct($message);
    }

    public static function forOrder(string $order, int $attemptsLeft): self
    {
        return new self(\sprintf('The code given for the order "%s" is not its pickup code; %d attempts left.', $order, $attemptsLeft), $attemptsLeft);
    }
}
