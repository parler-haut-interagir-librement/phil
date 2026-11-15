<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * The public profiles of the members, for their moderation: what the queue shows of a profile, and what a decision does
 * to it — `Hide` hides the public profile or resets its display name, avatar and description, `Restore` shows it
 * again, `Remove` empties them.
 */
interface MemberProfiles
{
    public function describe(MemberId $member): ?TargetView;

    public function apply(MemberId $member, DecisionAction $action): void;
}
