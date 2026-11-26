<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

use DateTimeImmutable;

/**
 * A holiday of the seasonal decor shown on a day: its key, its title, the decor drawn, whether it is a sober one, and
 * the first day of the span it falls in.
 */
final readonly class Festivity
{
    public function __construct(
        public string $key,
        public string $title,
        public string $decor,
        public bool $sober,
        public DateTimeImmutable $start,
    ) {
    }

    /**
     * What tells this year's span of the holiday from the others: its key and its first day, under which the closing of
     * the decor is remembered, so that a decor closed one year shows again the next.
     */
    public function id(): string
    {
        return $this->key.'-'.$this->start->format('Y-m-d');
    }
}
