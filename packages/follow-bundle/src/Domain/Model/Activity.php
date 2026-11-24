<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Model;

use DateTimeImmutable;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Something new on a subject, as the application tells it: its identifier, the subject, the kind of event, who did it
 * and the members it mentions, by identifier only. Kept until it is sent to the followers and its retention ends.
 */
final class Activity
{
    public const int MAX_MENTIONS = 200;

    public readonly string $id;

    public readonly string $event;

    public readonly ?string $actor;

    /** @var list<string> */
    public readonly array $mentions;

    /**
     * @param list<string> $mentions
     *
     * @throws InvalidFollow when an identifier or the event is of the wrong shape, or it mentions too many members
     */
    public function __construct(
        string $id,
        public readonly SubjectReference $subject,
        string $event,
        ?string $actor,
        array $mentions,
        public readonly DateTimeImmutable $occurredAt,
    ) {
        $this->id = Identifier::id($id, 'activity');
        $this->event = Identifier::key($event, 'event');
        $this->actor = null === $actor ? null : Identifier::id($actor, 'actor');

        if (\count($mentions) > self::MAX_MENTIONS) {
            throw InvalidFollow::because(\sprintf('An activity mentions %d members at most.', self::MAX_MENTIONS));
        }

        $this->mentions = array_values(array_unique(array_map(static fn (string $member): string => Identifier::id($member, 'mentioned member'), $mentions)));
    }

    public function mentions(string $member): bool
    {
        return \in_array($member, $this->mentions, true);
    }
}
