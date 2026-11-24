<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Symfony\Component\Uid\Uuid;

/**
 * A poll of availabilities: a title, the dates proposed — its slots, kept apart —, the time zone it is shown in, its
 * organizer, its deadline, and once closed, who closed it and the date chosen, if any.
 *
 * Instants are kept in UTC and compared by order; the time zone only shows them. Whether the organizer sees the answers
 * before the closing, and who else sees them, are fixed at the creation.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_scheduling_poll_due', columns: ['closed_at', 'deadline'])]
final class SchedulingPoll
{
    public const string TABLE = 'phil_scheduling_poll';

    public const int MAX_TITLE = 200;

    public const int MAX_DESCRIPTION = 4000;

    public const int MAX_LOCATION = 255;

    #[ORM\Column(name: 'closed_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Column(name: 'closed_by', length: 16, nullable: true, enumType: ClosedBy::class)]
    private ?ClosedBy $closedBy = null;

    #[ORM\Column(name: 'chosen_slot', length: 36, nullable: true, options: ['fixed' => true])]
    private ?string $chosenSlot = null;

    #[ORM\Column(name: 'title', length: self::MAX_TITLE)]
    private readonly string $title;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private readonly ?string $description;

    #[ORM\Column(name: 'location', length: self::MAX_LOCATION, nullable: true)]
    private readonly ?string $location;

    #[ORM\Column(name: 'organizer', length: 320)]
    private readonly string $organizer;

    #[ORM\Column(name: 'timezone', length: 64)]
    private readonly string $timezone;

    #[ORM\Column(name: 'deadline', type: 'datetime_utc_immutable')]
    private DateTimeImmutable $deadline;

    #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @throws InvalidSchedulingPoll when the title, the texts or the time zone are out of bounds, or the deadline is not after the creation
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        string $title,
        ResourceReference $organizer,
        string $timezone,
        DateTimeImmutable $deadline,
        DateTimeImmutable $createdAt,
        ?string $description = null,
        ?string $location = null,
        #[ORM\Column(name: 'organizer_sees_answers')]
        private readonly bool $organizerSeesAnswers = true,
        #[ORM\Column(name: 'results', length: 16, enumType: ResultsVisibility::class)]
        private readonly ResultsVisibility $results = ResultsVisibility::Always,
    ) {
        if (!Uuid::isValid($id)) {
            throw InvalidSchedulingPoll::because('A scheduling poll is identified by a UUID.');
        }

        $title = trim($title);

        if ('' === $title || mb_strlen($title) > self::MAX_TITLE || !mb_check_encoding($title, 'UTF-8')) {
            throw InvalidSchedulingPoll::because(\sprintf('The title of a scheduling poll is 1 to %d characters.', self::MAX_TITLE));
        }

        if (mb_strlen((string) $description) > self::MAX_DESCRIPTION || mb_strlen((string) $location) > self::MAX_LOCATION) {
            throw InvalidSchedulingPoll::because(\sprintf('The description of a scheduling poll is %d characters at most, its place %d.', self::MAX_DESCRIPTION, self::MAX_LOCATION));
        }

        self::zone($timezone);

        if ($deadline <= $createdAt) {
            throw InvalidSchedulingPoll::because('The deadline of a scheduling poll comes after its creation.');
        }

        $this->title = $title;
        $this->description = '' === trim((string) $description) ? null : $description;
        $this->location = '' === trim((string) $location) ? null : $location;
        $this->organizer = $organizer->toString();
        $this->timezone = $timezone;
        $this->deadline = self::utc($deadline);
        $this->createdAt = self::utc($createdAt);
    }

    /**
     * The time zone of this name.
     *
     * @throws InvalidSchedulingPoll when the name is no time zone of the IANA database
     */
    public static function zone(string $name): DateTimeZone
    {
        if (!\in_array($name, DateTimeZone::listIdentifiers(), true)) {
            throw InvalidSchedulingPoll::because(\sprintf('"%s" is not a time zone of the IANA database, such as "America/Toronto".', mb_substr($name, 0, 64)));
        }

        return new DateTimeZone($name);
    }

    public static function utc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone('UTC'));
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function organizer(): ResourceReference
    {
        return ResourceReference::fromString($this->organizer);
    }

    public function isOrganizedBy(ResourceReference $actor): bool
    {
        return $this->organizer === $actor->toString();
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }

    public function deadline(): DateTimeImmutable
    {
        return $this->deadline;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function organizerSeesAnswers(): bool
    {
        return $this->organizerSeesAnswers;
    }

    public function resultsVisibility(): ResultsVisibility
    {
        return $this->results;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedBy(): ?ClosedBy
    {
        return $this->closedBy;
    }

    /**
     * The identifier of the slot chosen, `null` while the poll is open or when it closed without a date.
     */
    public function chosenSlot(): ?string
    {
        return $this->chosenSlot;
    }

    /**
     * Whether answers can change at this moment: not closed, and before the deadline.
     */
    public function isOpenAt(DateTimeImmutable $now): bool
    {
        return !$this->closedAt instanceof DateTimeImmutable && $now < $this->deadline;
    }

    /**
     * Whether it is closed for good, by hand or by the task that closes the polls past their deadline. A poll past its
     * deadline that the task has not closed yet is neither open nor closed: its date is about to be chosen.
     */
    public function isClosed(): bool
    {
        return $this->closedAt instanceof DateTimeImmutable;
    }

    /**
     * Records the closing, as the store wrote it in the one conditional write that closes the poll.
     */
    public function close(DateTimeImmutable $at, ClosedBy $by, ?string $slot): void
    {
        $this->closedAt = self::utc($at);
        $this->closedBy = $by;
        $this->chosenSlot = $slot;
    }
}
