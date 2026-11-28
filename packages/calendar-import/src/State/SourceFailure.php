<?php

declare(strict_types=1);

namespace Phil\CalendarImport\State;

use DateTimeImmutable;
use Phil\CalendarImport\Source\FailureKind;

/**
 * A read that gave no calendar: why, when, and the status the source answered with, if it answered.
 */
final readonly class SourceFailure
{
    public function __construct(
        public FailureKind $kind,
        public string $message,
        public DateTimeImmutable $at,
        public ?int $status = null,
    ) {
    }
}
