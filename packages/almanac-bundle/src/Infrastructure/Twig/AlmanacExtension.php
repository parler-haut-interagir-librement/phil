<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Infrastructure\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Almanac\ComputedDate;
use Phil\AlmanacBundle\Application\AlmanacFactory;
use Twig\Attribute\AsTwigFunction;

/**
 * The almanac in Twig: `almanac_dates(from, until, tags)`.
 */
final readonly class AlmanacExtension
{
    public function __construct(
        private AlmanacFactory $almanac,
    ) {
    }

    /**
     * The dates that touch a period, its end excluded, by start then title. A bound is a date, mutable or not, taken as
     * the instant it is, or text read in the almanac's time zone: `'2026-10-01'` is its midnight there.
     *
     * @param list<string>|null $tags
     *
     * @return list<ComputedDate>
     */
    #[AsTwigFunction('almanac_dates')]
    public function dates(DateTimeInterface|string $from, DateTimeInterface|string $until, ?array $tags = null): array
    {
        return $this->almanac->almanac()->between($this->instant($from), $this->instant($until), $tags);
    }

    private function instant(DateTimeInterface|string $bound): DateTimeImmutable
    {
        return \is_string($bound) ? new DateTimeImmutable($bound, $this->almanac->zone()) : DateTimeImmutable::createFromInterface($bound);
    }
}
