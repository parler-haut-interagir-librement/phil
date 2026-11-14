<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Contract;

/**
 * Where each feed stopped in each segment: the sequence of the last row it sent. A segment's rows are numbered without
 * a gap and never change, so the next run starts right after it, however late a row's instant.
 */
interface FeedCheckpoints
{
    /**
     * @return int<0, max> the sequence of the last row the feed sent in the segment; 0 when it sent none
     */
    public function position(string $feed, string $segment): int;

    /**
     * Moves the feed's place in the segment forward; a sequence at or below its place leaves it where it is.
     */
    public function advance(string $feed, string $segment, int $sequence): void;
}
