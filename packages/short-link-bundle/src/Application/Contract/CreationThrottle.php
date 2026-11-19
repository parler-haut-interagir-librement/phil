<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Contract;

/**
 * How many links one author may create in a while.
 */
interface CreationThrottle
{
    /**
     * Spends one creation of the author's allowance.
     *
     * @param string $author an identifier of whoever creates the link — an account, a hashed address —, never kept
     *                       beyond the window of the allowance
     *
     * @return bool whether the creation is allowed
     */
    public function consume(string $author): bool;
}
