<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Contract;

/**
 * Whether each member shows or hides the list of their followers; a member who never chose has no row.
 */
interface FollowerVisibilityRepository
{
    public function hidden(string $member): ?bool;

    public function choose(string $member, bool $hidden): void;

    public function eraseMember(string $member): void;
}
