<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Model;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Symfony\Component\Uid\Uuid;

/**
 * A date proposed by a scheduling poll: its start and its end, excluded, kept in UTC, and its place in the poll.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_scheduling_poll_slot_poll', columns: ['poll', 'position'])]
final class Slot
{
    public const string TABLE = 'phil_scheduling_poll_slot';

    /** The longest a proposed date lasts. */
    public const string MAX_DURATION = 'P14D';

    #[ORM\Column(name: 'starts_at', type: 'datetime_utc_immutable')]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at', type: 'datetime_utc_immutable')]
    private DateTimeImmutable $endsAt;

    /**
     * @throws InvalidSchedulingPoll when the end is not after the start, or comes more than fourteen days after it
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'poll', length: 36, options: ['fixed' => true])]
        public readonly string $poll,
        #[ORM\Column(name: 'position', type: 'smallint')]
        public readonly int $position,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ) {
        if (!Uuid::isValid($id) || !Uuid::isValid($poll)) {
            throw InvalidSchedulingPoll::because('A proposed date and its poll are identified by UUIDs.');
        }

        if ($endsAt <= $startsAt || $endsAt > $startsAt->add(new DateInterval(self::MAX_DURATION))) {
            throw InvalidSchedulingPoll::because('A proposed date ends after it starts, fourteen days later at the most.');
        }

        $this->startsAt = SchedulingPoll::utc($startsAt);
        $this->endsAt = SchedulingPoll::utc($endsAt);
    }

    /**
     * The start, in UTC or in the time zone given.
     */
    public function startsAt(?DateTimeZone $timezone = null): DateTimeImmutable
    {
        return $timezone instanceof DateTimeZone ? $this->startsAt->setTimezone($timezone) : $this->startsAt;
    }

    /**
     * The end, excluded, in UTC or in the time zone given.
     */
    public function endsAt(?DateTimeZone $timezone = null): DateTimeImmutable
    {
        return $timezone instanceof DateTimeZone ? $this->endsAt->setTimezone($timezone) : $this->endsAt;
    }
}
