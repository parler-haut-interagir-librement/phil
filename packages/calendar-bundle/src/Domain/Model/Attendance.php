<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Symfony\Component\Uid\Uuid;

/**
 * A member who attends an event: one of its occurrences, by its start in UTC, or all of them. One row per event,
 * occurrence and member.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_calendar_attendance_unique', columns: ['event', 'occurrence', 'member'])]
#[ORM\Index(name: 'phil_calendar_attendance_member', columns: ['member'])]
final class Attendance
{
    public const string TABLE = 'phil_calendar_attendance';

    /** The occurrence of an attendance to every occurrence. */
    public const string EVERY_OCCURRENCE = '';

    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
    public readonly string $id;

    #[ORM\Column(name: 'member', length: 129)]
    private readonly string $member;

    #[ORM\Column(name: 'occurrence', length: 19)]
    private readonly string $occurrence;

    #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
    private readonly DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        public readonly Event $event,
        ?DateTimeImmutable $occurrence,
        Member $member,
        DateTimeImmutable $at,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->occurrence = self::key($occurrence);
        $this->member = $member->toString();
        $this->createdAt = $at->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The occurrence as it is kept: its start in UTC, or nothing for every occurrence.
     */
    public static function key(?DateTimeImmutable $occurrence): string
    {
        return $occurrence?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s') ?? self::EVERY_OCCURRENCE;
    }

    public function member(): Member
    {
        return Member::fromString($this->member);
    }

    /**
     * The start of the occurrence attended, in the event's time zone; null for every occurrence.
     */
    public function occurrence(): ?DateTimeImmutable
    {
        return self::EVERY_OCCURRENCE === $this->occurrence ? null : new DateTimeImmutable($this->occurrence, new DateTimeZone('UTC'))->setTimezone($this->event->timezone());
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
