<?php

declare(strict_types=1);

namespace Phil\CalendarImport\State;

use DateTimeImmutable;
use Phil\CalendarImport\Source\FetchValidators;

/**
 * What the importer knows of a source between two reads: the last snapshot it read, the validators of the last answer,
 * when it was last checked and last read, and its failure since, dated, with the failures in a row. A failure keeps
 * the snapshot: the last good state stays until a read replaces it.
 */
final readonly class SourceState
{
    public function __construct(
        public string $sourceId,
        public ?Snapshot $snapshot = null,
        public FetchValidators $validators = new FetchValidators(),
        public ?DateTimeImmutable $checkedAt = null,
        public ?DateTimeImmutable $succeededAt = null,
        public ?SourceFailure $failure = null,
        public int $failures = 0,
    ) {
    }

    public function succeeded(Snapshot $snapshot, FetchValidators $validators, DateTimeImmutable $at): self
    {
        return new self($this->sourceId, $snapshot, $validators, $at, $at);
    }

    public function notModified(FetchValidators $validators, DateTimeImmutable $at): self
    {
        return new self($this->sourceId, $this->snapshot, $validators, $at, $at);
    }

    public function failed(SourceFailure $failure): self
    {
        return new self($this->sourceId, $this->snapshot, $this->validators, $failure->at, $this->succeededAt, $failure, $this->failures + 1);
    }

    public function isFailing(): bool
    {
        return $this->failure instanceof SourceFailure;
    }
}
