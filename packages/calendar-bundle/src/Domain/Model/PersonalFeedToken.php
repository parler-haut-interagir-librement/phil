<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Symfony\Component\Uid\Uuid;

/**
 * The secret of a member's own iCalendar feed, one per member, kept as its SHA-256 digest: the secret itself is shown
 * once, when it is issued, and cannot be read back from the table. When it was issued, as a moment in UTC.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_calendar_feed_token_member', columns: ['member'])]
#[ORM\UniqueConstraint(name: 'phil_calendar_feed_token_digest', columns: ['digest'])]
final class PersonalFeedToken
{
    public const string TABLE = 'phil_calendar_feed_token';

    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
    public readonly string $id;

    #[ORM\Column(name: 'member', length: 129)]
    private readonly string $member;

    public function __construct(
        Member $member,
        #[ORM\Column(name: 'digest', length: 64, options: ['fixed' => true])]
        public readonly string $digest,
        #[ORM\Column(name: 'issued_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $issuedAt,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->member = $member->toString();
    }

    public function member(): Member
    {
        return Member::fromString($this->member);
    }
}
