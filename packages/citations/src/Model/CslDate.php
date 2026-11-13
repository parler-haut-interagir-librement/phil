<?php

declare(strict_types=1);

namespace Phil\Citations\Model;

use Phil\Citations\Exception\InvalidReference;

/**
 * A date variable of a reference: a partial date or a range of two, approximate or not, or a literal text when the
 * date is not a calendar one (« Spring term », « forthcoming »).
 */
final readonly class CslDate
{
    /**
     * @throws InvalidReference a date with neither a start nor a literal, or a range without a start
     */
    public function __construct(
        public ?PartialDate $start = null,
        public ?PartialDate $end = null,
        public bool $circa = false,
        public ?string $literal = null,
    ) {
        if (!$start instanceof PartialDate && (null === $literal || $end instanceof PartialDate)) {
            throw InvalidReference::because('a date needs a start, or a literal alone');
        }
    }

    public static function year(int $year): self
    {
        return new self(new PartialDate($year));
    }

    /**
     * The year the date starts in; null for a literal date.
     */
    public function startYear(): ?int
    {
        return $this->start?->year;
    }
}
