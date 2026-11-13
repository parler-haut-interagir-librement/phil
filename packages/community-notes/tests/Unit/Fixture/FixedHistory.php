<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit\Fixture;

use Override;
use Phil\CommunityNotes\Contract\RatingHistory;
use Phil\CommunityNotes\PairHistory;
use Phil\Resource\ResourceReference;

/**
 * The same history for any two raters, the same rater twice included.
 */
final readonly class FixedHistory implements RatingHistory
{
    public function __construct(
        private PairHistory $history,
    ) {
    }

    #[Override]
    public function between(ResourceReference $first, ResourceReference $second, string $exceptNote): PairHistory
    {
        return $this->history;
    }
}
