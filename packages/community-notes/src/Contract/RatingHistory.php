<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Contract;

use Phil\CommunityNotes\PairHistory;
use Phil\Resource\ResourceReference;

/**
 * What two raters did on the notes they both rated, the note being decided left out.
 */
interface RatingHistory
{
    public function between(ResourceReference $first, ResourceReference $second, string $exceptNote): PairHistory;
}
