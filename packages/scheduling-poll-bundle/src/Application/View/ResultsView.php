<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\View;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The answers of a scheduling poll as a page shows them to one viewer: the dates in the poll's order with their counts,
 * one row per respondent who answered, and the date chosen once closed. While the answers are not the viewer's to see,
 * `visible` is false, the counts are `null` and there is no row: the page says so.
 */
final readonly class ResultsView
{
    /**
     * @param list<SlotLine>    $slots
     * @param list<ResponseRow> $rows
     */
    public function __construct(
        public string $poll,
        public string $title,
        public DateTimeZone $timezone,
        public DateTimeImmutable $deadline,
        public array $slots,
        public array $rows,
        public int $answered,
        public bool $visible,
        public bool $closed,
        public ?SlotLine $chosen,
    ) {
    }
}
