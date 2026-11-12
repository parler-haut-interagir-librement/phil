<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

/**
 * Erases a user's identifier from the directory: from the listings it wrote or edits (`Listing::forget()`) and from
 * every version it wrote, whose `editedBy` becomes empty. `Infrastructure\Doctrine\DoctrineUserErasure` is the
 * Doctrine adapter.
 */
interface UserErasure
{
    /**
     * @return int how many listings and versions changed
     */
    public function erase(string $user): int;
}
