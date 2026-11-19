<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Repository;

use Phil\CalendarBundle\Domain\Model\PersonalFeedToken;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Where the secrets of members' feeds are kept, by their digest.
 */
interface PersonalFeedTokenRepository
{
    /**
     * Keeps a member's new token in place of the one they had.
     */
    public function replace(PersonalFeedToken $token): void;

    public function ofMember(Member $member): ?PersonalFeedToken;

    public function byDigest(string $digest): ?PersonalFeedToken;

    /**
     * Removes a member's token: their feed's address answers no more.
     */
    public function forgetMember(Member $member): void;
}
