<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\WaitlistBundle\Domain\Exception\InvalidWaitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Symfony\Component\Uid\Uuid;

/**
 * A participant's place in a waiting list, for one or more places. Its participant is an identifier the application
 * gives — an account, an order, a ticket —, never a name or an address: letters, digits, `_`, `.`, `:` and `-`, so that
 * an e-mail address is refused.
 *
 * Its rank orders the list. In a queue, it is the moment it joined, in UTC to the microsecond, then its identifier. In a
 * draw, the entries made before the draw take `d` and the SHA-256 of the seed, a line feed and their identifier, in
 * hexadecimal: anyone who has the published seed and the identifiers orders them again. A drawn rank comes before any
 * queued one.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_waitlist_entry_queue', columns: ['waitlist', 'status', 'queue_rank'])]
#[ORM\Index(name: 'phil_waitlist_entry_participant', columns: ['waitlist', 'participant'])]
#[ORM\Index(name: 'phil_waitlist_entry_deadline', columns: ['status', 'offer_expires_at'])]
final class Entry
{
    public const string TABLE = 'phil_waitlist_entry';

    /** The longest participant or subject identifier, in bytes. */
    public const int IDENTIFIER_LENGTH = 190;

    /** What starts the rank of an entry ranked by the moment it joined. */
    public const string QUEUED = 'q';

    /** What starts the rank of an entry ranked by a draw: it comes before every queued one. */
    public const string DRAWN = 'd';

    /** What an identifier looks like: no space, no `@`, starting with a letter or a digit. */
    private const string IDENTIFIER = '/^[A-Za-z0-9][A-Za-z0-9_.:\-]{0,189}$/D';

    #[ORM\Column(name: 'offered_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $offeredAt = null;

    #[ORM\Column(name: 'offer_expires_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $offerExpiresAt = null;

    #[ORM\Column(name: 'ended_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $endedAt = null;

    #[ORM\Column(name: 'status', length: 16, enumType: EntryStatus::class)]
    private EntryStatus $status = EntryStatus::Waiting;

    #[ORM\Column(name: 'queue_rank', length: 72)]
    private string $rank;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Waitlist::class)]
        #[ORM\JoinColumn(name: 'waitlist', nullable: false, onDelete: 'CASCADE')]
        private Waitlist $waitlist,
        #[ORM\Column(name: 'participant', length: self::IDENTIFIER_LENGTH)]
        private string $participant,
        #[ORM\Column(name: 'places')]
        private int $places,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
        $this->rank = self::QUEUED.$createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u').'|'.$id;
    }

    /**
     * A place in the list, behind the entries that joined before it.
     *
     * @throws InvalidWaitlist when the participant is not an identifier, or the places below one
     */
    public static function join(Waitlist $waitlist, string $participant, int $places, DateTimeImmutable $now): self
    {
        self::assertIdentifier($participant, 'A participant');

        if ($places < 1) {
            throw InvalidWaitlist::because('An entry waits for at least one place.');
        }

        return new self(Uuid::v7()->toRfc4122(), $waitlist, $participant, $places, $now);
    }

    /**
     * @throws InvalidWaitlist when the value is not an identifier
     */
    public static function assertIdentifier(string $value, string $what): void
    {
        if (1 !== preg_match(self::IDENTIFIER, $value)) {
            throw InvalidWaitlist::because(\sprintf('%s is a stable identifier of letters, digits, "_", ".", ":" and "-", %d characters at most, never an e-mail address.', $what, self::IDENTIFIER_LENGTH));
        }
    }

    public function waitlist(): Waitlist
    {
        return $this->waitlist;
    }

    public function participant(): string
    {
        return $this->participant;
    }

    public function places(): int
    {
        return $this->places;
    }

    public function status(): EntryStatus
    {
        return $this->status;
    }

    /**
     * What orders the list: the lower, the sooner.
     */
    public function rank(): string
    {
        return $this->rank;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function offeredAt(): ?DateTimeImmutable
    {
        return $this->offeredAt;
    }

    /**
     * When the offer made to it lapses, or lapsed; `null` before an offer.
     */
    public function offerExpiresAt(): ?DateTimeImmutable
    {
        return $this->offerExpiresAt;
    }

    /**
     * When the entry left the list: its offer accepted, declined or lapsed, or the entry left.
     */
    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    /**
     * Whether it holds an offer whose deadline has come.
     */
    public function hasExpiredAt(DateTimeImmutable $now): bool
    {
        return EntryStatus::Offered === $this->status && $this->offerExpiresAt instanceof DateTimeImmutable && $now >= $this->offerExpiresAt;
    }

    /**
     * Ranks a waiting entry by the seed of the draw.
     *
     * @throws InvalidWaitlist when it is not waiting
     */
    public function rankBy(string $seed): void
    {
        $this->assertStatus(EntryStatus::Waiting, 'Only a waiting entry is drawn');
        $this->rank = self::DRAWN.hash('sha256', $seed."\n".$this->id);
    }

    /**
     * Places offered until the deadline.
     *
     * @throws InvalidWaitlist when it is not waiting, or the deadline does not come after now
     */
    public function offer(DateTimeImmutable $now, DateTimeImmutable $deadline): void
    {
        $this->assertStatus(EntryStatus::Waiting, 'Only a waiting entry is offered places');

        if ($deadline <= $now) {
            throw InvalidWaitlist::because('An offer lapses after it is made.');
        }

        $this->status = EntryStatus::Offered;
        $this->offeredAt = $now;
        $this->offerExpiresAt = $deadline;
    }

    /**
     * @throws InvalidWaitlist when it holds no offer, or its deadline has come
     */
    public function accept(DateTimeImmutable $now): void
    {
        $this->assertStatus(EntryStatus::Offered, 'Only an offer is accepted');

        if ($this->hasExpiredAt($now)) {
            throw InvalidWaitlist::because('An offer is accepted before its deadline.');
        }

        $this->end(EntryStatus::Accepted, $now);
    }

    /**
     * @throws InvalidWaitlist when it holds no offer
     */
    public function decline(DateTimeImmutable $now): void
    {
        $this->assertStatus(EntryStatus::Offered, 'Only an offer is declined');
        $this->end(EntryStatus::Declined, $now);
    }

    /**
     * @throws InvalidWaitlist when it holds no offer, or its deadline has not come
     */
    public function lapse(DateTimeImmutable $now): void
    {
        if (!$this->hasExpiredAt($now)) {
            throw InvalidWaitlist::because('Only an offer whose deadline has come lapses.');
        }

        $this->end(EntryStatus::Lapsed, $now);
    }

    /**
     * @throws InvalidWaitlist when it is out of the list already
     */
    public function leave(DateTimeImmutable $now): void
    {
        if (!$this->status->isLive()) {
            throw InvalidWaitlist::because(\sprintf('An entry %s has left the list already.', $this->status->value));
        }

        $this->end(EntryStatus::Left, $now);
    }

    private function end(EntryStatus $status, DateTimeImmutable $now): void
    {
        $this->status = $status;
        $this->endedAt = $now;
    }

    /**
     * @throws InvalidWaitlist when the entry is not in that status
     */
    private function assertStatus(EntryStatus $expected, string $what): void
    {
        if ($expected !== $this->status) {
            throw InvalidWaitlist::because(\sprintf('%s, not an entry %s.', $what, $this->status->value));
        }
    }
}
