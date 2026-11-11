<?php

declare(strict_types=1);

namespace Phil\Listings\Contract;

use Phil\Listings\MemberId;

/**
 * Whether one of two members blocked the other: the application's list of blocks.
 */
interface BlockList
{
    /**
     * True when either member blocked the other, in either direction.
     */
    public function isBlocked(MemberId $one, MemberId $other): bool;
}
