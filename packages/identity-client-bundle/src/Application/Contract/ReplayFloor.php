<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Contract;

/**
 * The instant before which logout tokens and local sessions are refused, because the store that remembers accepted
 * logout tokens and ended provider sessions may have forgotten what came before it.
 */
interface ReplayFloor
{
    /**
     * The floor, in seconds since the Unix epoch. Reading it on a store that lost its record
     * records a new one: the store is cold, and nothing it held before can be trusted to be there.
     */
    public function floor(): int;
}
