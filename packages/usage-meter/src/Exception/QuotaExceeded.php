<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Exception;

use Phil\UsageMeter\Usage;
use RuntimeException;

/**
 * An action was refused before it ran: counting it would take the subject past its quota. Nothing was counted.
 *
 * The usage tells how much is used, the limit, and when the window next frees some of it; the message never names
 * the subject.
 */
final class QuotaExceeded extends RuntimeException implements UsageMeterException
{
    private function __construct(
        string $message,
        public readonly Usage $usage,
        public readonly int $requested,
    ) {
        parent::__construct($message);
    }

    public static function by(Usage $usage, int $requested): self
    {
        return new self(\sprintf(
            'The quota of "%s" is reached: %d used of %s, %d more requested; the window changes at %s.',
            $usage->meter,
            $usage->used,
            $usage->limit->toString(),
            $requested,
            $usage->resetsAt->format(\DATE_ATOM),
        ), $usage, $requested);
    }
}
