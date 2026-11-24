<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

/**
 * The notification types the bundle sends, declared in `phil_notifications.types`: a new activity on a followed subject;
 * the same while the member has not seen the subject since the last one that went out on every channel, kept to the
 * centre; a request to approve; a request accepted; a new follower of a member.
 */
final readonly class FollowTypes
{
    public const string ACTIVITY = 'follow.activity';

    public const string QUIET = 'follow.activity_quiet';

    public const string REQUESTED = 'follow.requested';

    public const string ACCEPTED = 'follow.accepted';

    public const string FOLLOWED = 'follow.followed';

    public function __construct(
        public string $activity = self::ACTIVITY,
        public string $quiet = self::QUIET,
        public string $requested = self::REQUESTED,
        public string $accepted = self::ACCEPTED,
        public string $followed = self::FOLLOWED,
    ) {
    }
}
