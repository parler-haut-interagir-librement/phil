<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Rule;

use DateTimeImmutable;
use Phil\ImportWizard\Contract\RowRule;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Profile\ProfileData;

/**
 * An end date that is not before its start date, when both are given: the end of an enrolment, of a membership.
 */
final readonly class DateOrder implements RowRule
{
    public function __construct(public string $start, public string $end)
    {
    }

    public static function name(): string
    {
        return 'date-order';
    }

    public static function fromArray(array $data): static
    {
        return new self(ProfileData::string($data, 'start', 'rule'), ProfileData::string($data, 'end', 'rule'));
    }

    public function check(array $values): array
    {
        $start = $values[$this->start] ?? null;
        $end = $values[$this->end] ?? null;

        if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $end < $start) {
            return [new CellError($this->end, Reason::DateOrder, $this->start)];
        }

        return [];
    }

    public function toArray(): array
    {
        return ['start' => $this->start, 'end' => $this->end];
    }
}
