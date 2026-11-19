<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application\View;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\View\Item;
use PHPUnit\Framework\TestCase;

/**
 * Items written briefly, in Toronto.
 */
abstract class ViewTestCase extends TestCase
{
    protected DateTimeZone $zone;

    protected function setUp(): void
    {
        $this->zone = new DateTimeZone('America/Toronto');
    }

    protected function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, $this->zone);
    }

    /**
     * A whole-day item from its first to its last day.
     *
     * @param list<string> $tags
     * @param list<string> $hosts
     */
    protected function days(string $title, string $first, ?string $last = null, array $tags = [], array $hosts = [], ?string $venue = null): Item
    {
        return new Item($title, $title, $this->day($first), $this->day($last ?? $first), true, tags: $tags, hosts: $hosts, venue: $venue);
    }

    /**
     * @param list<string> $tags
     * @param list<string> $hosts
     */
    protected function at(string $title, string $start, string $end, array $tags = [], array $hosts = [], ?string $venue = null): Item
    {
        return new Item($title, $title, $this->day($start), $this->day($end), false, tags: $tags, hosts: $hosts, venue: $venue);
    }
}
