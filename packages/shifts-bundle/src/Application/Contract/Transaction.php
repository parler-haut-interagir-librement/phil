<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application\Contract;

/**
 * Runs writes all at once or not at all: a roster and the offer of the swap board that passed one of its places.
 */
interface Transaction
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed;
}
