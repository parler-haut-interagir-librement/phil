<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Source;

use Phil\CalendarImport\ICalendar\ReadResult;

/**
 * What a source gave: a calendar read, or word that nothing changed since the validators sent — a `304 Not Modified`.
 */
final readonly class Fetched
{
    private function __construct(
        public ?ReadResult $result,
        public FetchValidators $validators,
    ) {
    }

    public static function read(ReadResult $result, FetchValidators $validators = new FetchValidators()): self
    {
        return new self($result, $validators);
    }

    public static function notModified(FetchValidators $validators): self
    {
        return new self(null, $validators);
    }

    public function isNotModified(): bool
    {
        return !$this->result instanceof ReadResult;
    }
}
