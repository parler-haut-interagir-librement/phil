<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Model;

use DateTimeImmutable;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * A member's choice about one subject — a content item, a thread, a listing, a member —: the level they follow it at,
 * their preferences by event at the custom level, and since when. One per member and subject.
 */
final class Follow
{
    public readonly string $member;

    private EventPreferences $events;

    /**
     * @param EventPreferences $events kept at the custom level only
     *
     * @throws InvalidFollow when the member is not an identifier
     */
    public function __construct(
        string $member,
        public readonly SubjectReference $subject,
        private FollowLevel $level,
        EventPreferences $events,
        public readonly DateTimeImmutable $followedAt,
    ) {
        $this->member = Identifier::id($member, 'member');
        $this->events = FollowLevel::Custom === $level ? $events : new EventPreferences();
    }

    public function level(): FollowLevel
    {
        return $this->level;
    }

    public function events(): EventPreferences
    {
        return $this->events;
    }

    /**
     * Takes a new level and its preferences; the preferences are kept only at the custom level.
     *
     * @return bool whether anything changed
     */
    public function change(FollowLevel $level, EventPreferences $events): bool
    {
        $events = FollowLevel::Custom === $level ? $events : new EventPreferences();

        if ($level === $this->level && $events->levels === $this->events->levels) {
            return false;
        }

        $this->level = $level;
        $this->events = $events;

        return true;
    }

    /**
     * Whether an activity of this kind reaches the member, mentioned in it or not.
     */
    public function notifies(string $event, bool $mentioned): bool
    {
        $level = FollowLevel::Custom === $this->level ? $this->events->levelFor($event) : $this->level;

        return $level->notifies($mentioned);
    }
}
