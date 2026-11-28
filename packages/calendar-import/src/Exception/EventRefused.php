<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

use Exception;
use Phil\CalendarImport\ICalendar\Rejection;

/**
 * Thrown inside the reader to stop reading one event; caught there, and turned into a `RejectedEvent`. It never leaves
 * the reader.
 *
 * @internal
 */
final class EventRefused extends Exception
{
    public function __construct(
        public readonly Rejection $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function because(Rejection $reason, string $message): self
    {
        return new self($reason, $message);
    }
}
