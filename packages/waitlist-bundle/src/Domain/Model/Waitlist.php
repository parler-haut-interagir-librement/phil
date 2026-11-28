<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\WaitlistBundle\Domain\Exception\InvalidWaitlist;
use Phil\WaitlistBundle\Domain\Status\WaitlistMode;
use Symfony\Component\Uid\Uuid;

/**
 * The waiting list of one thing with a capacity — a session, a workshop, an event, a product —, named by the
 * application as a subject: a kind (`session`) and an identifier. The list never counts the capacity itself: the
 * application tells it how many places free up, and the list offers them to its entries, holding those it cannot offer
 * yet.
 *
 * Its places held are those freed and not offered: nobody waits, the next entry needs more, or the draw is not made.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_waitlist_subject', columns: ['subject_type', 'subject_id'])]
final class Waitlist
{
    public const string TABLE = 'phil_waitlist';

    /** The longest subject kind, in bytes. */
    public const int SUBJECT_TYPE_LENGTH = 64;

    /** The shortest offer window, in seconds: a minute. */
    public const int MIN_OFFER_WINDOW = 60;

    /** The longest offer window, in seconds: thirty days. */
    public const int MAX_OFFER_WINDOW = 2_592_000;

    /** The most places a list holds at once. */
    public const int MAX_HELD = 1_000_000;

    /** A subject kind: lowercase letters, digits, `_`, `.` and `-`, starting with a letter. */
    private const string SUBJECT_TYPE = '/^[a-z][a-z0-9_.\-]{0,63}$/D';

    /** A published seed: 8 to 64 letters, digits, `_`, `.`, `:` and `-`. */
    private const string SEED = '/^[A-Za-z0-9_.:\-]{8,64}$/D';

    #[ORM\Column(name: 'held_places')]
    private int $heldPlaces = 0;

    #[ORM\Column(name: 'closed_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Column(name: 'draw_seed', length: 64, nullable: true)]
    private ?string $drawSeed = null;

    #[ORM\Column(name: 'drawn_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $drawnAt = null;

    /** Counts the requests that took the list's lock, one at a time. */
    #[ORM\Column(name: 'claims')]
    private int $claims = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'subject_type', length: self::SUBJECT_TYPE_LENGTH)]
        private string $subjectType,
        #[ORM\Column(name: 'subject_id', length: Entry::IDENTIFIER_LENGTH)]
        private string $subjectId,
        #[ORM\Column(name: 'list_mode', length: 16, enumType: WaitlistMode::class)]
        private WaitlistMode $mode,
        #[ORM\Column(name: 'offer_window')]
        private int $offerWindow,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param int $offerWindow how long an entry holds an offer, in seconds
     *
     * @throws InvalidWaitlist when the subject is not a kind and an identifier, or the window out of bounds
     */
    public static function open(string $subjectType, string $subjectId, WaitlistMode $mode, int $offerWindow, DateTimeImmutable $now): self
    {
        if (1 !== preg_match(self::SUBJECT_TYPE, $subjectType)) {
            throw InvalidWaitlist::because(\sprintf('A subject kind is lowercase letters, digits, "_", "." and "-", starting with a letter, %d characters at most.', self::SUBJECT_TYPE_LENGTH));
        }

        Entry::assertIdentifier($subjectId, 'A subject identifier');
        self::assertWindow($offerWindow);

        return new self(Uuid::v7()->toRfc4122(), $subjectType, $subjectId, $mode, $offerWindow, $now);
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function mode(): WaitlistMode
    {
        return $this->mode;
    }

    /**
     * How long an entry holds an offer, in seconds.
     */
    public function offerWindow(): int
    {
        return $this->offerWindow;
    }

    public function heldPlaces(): int
    {
        return $this->heldPlaces;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function isClosed(): bool
    {
        return $this->closedAt instanceof DateTimeImmutable;
    }

    /**
     * The seed of the draw, published so that anyone can order the entries again; `null` before the draw.
     */
    public function drawSeed(): ?string
    {
        return $this->drawSeed;
    }

    public function drawnAt(): ?DateTimeImmutable
    {
        return $this->drawnAt;
    }

    /**
     * Whether the list offers places: always in a queue, once drawn in a draw.
     */
    public function isOffering(): bool
    {
        return WaitlistMode::Queue === $this->mode || null !== $this->drawSeed;
    }

    /**
     * When an offer made now lapses.
     */
    public function offerDeadline(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify(\sprintf('+%d seconds', $this->offerWindow));
    }

    /**
     * @throws InvalidWaitlist when the window is out of bounds
     */
    public function changeOfferWindow(int $seconds): void
    {
        self::assertWindow($seconds);
        $this->offerWindow = $seconds;
    }

    /**
     * Places freed, held until they are offered.
     *
     * @throws InvalidWaitlist when the places are below one, or the list would hold too many
     */
    public function hold(int $places): void
    {
        if ($places < 1) {
            throw InvalidWaitlist::because('At least one place is freed.');
        }

        if ($places > self::MAX_HELD - $this->heldPlaces) {
            throw InvalidWaitlist::because(\sprintf('A waiting list holds %d places at most.', self::MAX_HELD));
        }

        $this->heldPlaces += $places;
    }

    /**
     * Places held taken back: offered to an entry, or reclaimed by the application.
     *
     * @throws InvalidWaitlist when the places are below one or more than held
     */
    public function take(int $places): void
    {
        if ($places < 1 || $places > $this->heldPlaces) {
            throw InvalidWaitlist::because(\sprintf('From 1 to the %d places held are taken.', $this->heldPlaces));
        }

        $this->heldPlaces -= $places;
    }

    /**
     * Takes no more entries; those in the list keep their turn.
     *
     * @throws InvalidWaitlist when it is already closed
     */
    public function close(DateTimeImmutable $now): void
    {
        if ($this->isClosed()) {
            throw InvalidWaitlist::because('A closed waiting list is not closed again.');
        }

        $this->closedAt = $now;
    }

    /**
     * @throws InvalidWaitlist when it is open
     */
    public function reopen(): void
    {
        if (!$this->isClosed()) {
            throw InvalidWaitlist::because('Only a closed waiting list is reopened.');
        }

        $this->closedAt = null;
    }

    /**
     * Records the draw; the entries are ordered by its seed.
     *
     * @throws InvalidWaitlist when the list is not a draw, is drawn already, or the seed is not one
     */
    public function draw(string $seed, DateTimeImmutable $now): void
    {
        if (WaitlistMode::Draw !== $this->mode) {
            throw InvalidWaitlist::because('Only a waiting list in draw mode is drawn.');
        }

        if (null !== $this->drawSeed) {
            throw InvalidWaitlist::because('A waiting list is drawn once.');
        }

        if (1 !== preg_match(self::SEED, $seed)) {
            throw InvalidWaitlist::because('A seed is 8 to 64 letters, digits, "_", ".", ":" and "-".');
        }

        $this->drawSeed = $seed;
        $this->drawnAt = $now;
    }

    /**
     * @throws InvalidWaitlist when the window is out of bounds
     */
    private static function assertWindow(int $seconds): void
    {
        if ($seconds < self::MIN_OFFER_WINDOW || $seconds > self::MAX_OFFER_WINDOW) {
            throw InvalidWaitlist::because(\sprintf('An offer is held from %d to %d seconds.', self::MIN_OFFER_WINDOW, self::MAX_OFFER_WINDOW));
        }
    }
}
