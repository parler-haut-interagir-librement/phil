<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ProductFeeds\Exception\InvalidFeedItem;

/**
 * When a sale price applies: from its start, included, to its end, excluded, written in UTC.
 */
final readonly class SalePeriod
{
    public DateTimeImmutable $from;

    public DateTimeImmutable $until;

    public function __construct(DateTimeImmutable $from, DateTimeImmutable $until)
    {
        if ($until <= $from) {
            throw InvalidFeedItem::because('A sale ends after it starts.');
        }

        $utc = new DateTimeZone('UTC');
        $this->from = $from->setTimezone($utc);
        $this->until = $until->setTimezone($utc);
    }

    /**
     * The period as an ISO 8601 interval, `2026-11-27T05:00:00+00:00/2026-12-01T05:00:00+00:00`.
     */
    public function toString(): string
    {
        return $this->from->format(\DATE_ATOM).'/'.$this->until->format(\DATE_ATOM);
    }
}
