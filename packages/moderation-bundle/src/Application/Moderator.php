<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * A moderator: the member, and the role they act under.
 */
final readonly class Moderator
{
    public function __construct(
        public MemberId $member,
        public Role $role,
    ) {
    }
}
