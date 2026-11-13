<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Poll\BallotKind;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Exception\InvalidPollDefinition;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;

/**
 * A question put to a vote: its options and their labels, the rules of its ballots, the method its ballots are
 * counted by, whether ballots are anonymous, who sees the results, and when it is open. It may belong to a resource
 * of the application — an idea, a meeting, a survey — named by its subject.
 *
 * A poll counted by a method that fills several seats, the single transferable vote, names how many; every other
 * poll fills one. Its revision goes up with every ballot cast, changed or withdrawn, so that a result kept between two
 * counts is known to be the one of its ballots.
 *
 * A poll is open from its opening, or from its creation when it has none, until its closing, or until it is closed by
 * hand. Its options and rules never change once it is created, so that the ballots cast stay valid; its dates may.
 * Times are UTC, to the second.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['subject'])]
final class Poll
{
    public const string TABLE = 'phil_poll';

    public const int MAX_QUESTION = 500;

    #[ORM\Column(name: 'seats', type: 'smallint', options: ['default' => 1])]
    private int $seats = 1;

    /** Read and raised by the store, in the table, so that two ballots at once each raise it. */
    #[ORM\Column(name: 'revision', type: 'bigint', options: ['default' => 0])]
    private int $revision = 0;

    #[ORM\Column(name: 'opens_at', nullable: true)]
    private ?DateTimeImmutable $opensAt = null;

    #[ORM\Column(name: 'closes_at', nullable: true)]
    private ?DateTimeImmutable $closesAt = null;

    #[ORM\Column(name: 'closed_at', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    /**
     * @param list<array{id: string, label: string}> $options
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        public readonly string $id,
        #[ORM\Column(name: 'question', type: 'text')]
        private readonly string $question,
        #[ORM\Column(name: 'options', type: 'json')]
        private readonly array $options,
        #[ORM\Column(name: 'kind', length: 16, enumType: BallotKind::class)]
        private readonly BallotKind $kind,
        #[ORM\Column(name: 'min_choices', type: 'smallint')]
        private readonly int $minChoices,
        #[ORM\Column(name: 'max_choices', type: 'smallint')]
        private readonly int $maxChoices,
        #[ORM\Column(name: 'abstention')]
        private readonly bool $abstention,
        #[ORM\Column(name: 'counting', length: 64)]
        private readonly string $counting,
        #[ORM\Column(name: 'anonymous')]
        private readonly bool $anonymous,
        #[ORM\Column(name: 'results', length: 16, enumType: ResultsVisibility::class)]
        private readonly ResultsVisibility $results,
        #[ORM\Column(name: 'subject', length: 320, nullable: true)]
        private readonly ?string $subject,
        #[ORM\Column(name: 'created_by', length: 320)]
        private readonly string $createdBy,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * A new poll. The options are those of the rules, in their order, each with its label.
     *
     * @param list<PollOption> $options
     *
     * @throws InvalidPollDefinition when the identifier is not 1 to 36 characters, the question not 1 to 500, the
     *                               options not those of the rules, the counting method misnamed, the seats not 1 to
     *                               one fewer than the options, or the poll closing before it opens
     */
    public static function create(
        string $id,
        string $question,
        array $options,
        PollRules $rules,
        string $counting,
        bool $anonymous,
        ResultsVisibility $results,
        ResourceReference $createdBy,
        DateTimeImmutable $now,
        ?ResourceReference $subject = null,
        ?DateTimeImmutable $opensAt = null,
        ?DateTimeImmutable $closesAt = null,
        int $seats = 1,
    ): self {
        if ('' === $id || \strlen($id) > 36) {
            throw InvalidPollDefinition::because('A poll is identified by 1 to 36 characters.');
        }

        if ('' === trim($question) || !mb_check_encoding($question, 'UTF-8') || mb_strlen($question) > self::MAX_QUESTION) {
            throw InvalidPollDefinition::because(\sprintf('The question of a poll is a UTF-8 text of 1 to %d characters.', self::MAX_QUESTION));
        }

        if (array_map(static fn (PollOption $option): string => $option->id, $options) !== $rules->options) {
            throw InvalidPollDefinition::because('The options of a poll are those of its rules, in their order.');
        }

        if (1 !== preg_match('/^[a-z0-9_]{1,64}$/D', $counting)) {
            throw InvalidPollDefinition::because('A counting method is named by 1 to 64 lowercase ASCII letters, digits or "_".');
        }

        if ($seats < 1 || $seats >= \count($options)) {
            throw InvalidPollDefinition::because(\sprintf('A poll fills 1 to %d seats, one fewer than its options, not %d.', \count($options) - 1, $seats));
        }

        $poll = new self(
            $id,
            $question,
            array_map(static fn (PollOption $option): array => ['id' => $option->id, 'label' => $option->label], $options),
            $rules->kind,
            $rules->minChoices,
            $rules->maxChoices,
            $rules->abstention,
            $counting,
            $anonymous,
            $results,
            $subject?->toString(),
            $createdBy->toString(),
            self::second($now),
        );
        $poll->reschedule($opensAt, $closesAt);
        $poll->seats = $seats;

        return $poll;
    }

    public function question(): string
    {
        return $this->question;
    }

    /**
     * @return list<PollOption>
     */
    public function options(): array
    {
        return array_map(static fn (array $option): PollOption => PollOption::of($option['id'], $option['label']), $this->options);
    }

    /**
     * The label of an option, `null` for an option the poll does not have.
     */
    public function label(string $option): ?string
    {
        foreach ($this->options as $kept) {
            if ($kept['id'] === $option) {
                return $kept['label'];
            }
        }

        return null;
    }

    public function rules(): PollRules
    {
        return PollRules::of($this->kind, array_column($this->options, 'id'), $this->minChoices, $this->maxChoices, $this->abstention);
    }

    /**
     * The name of the method the ballots are counted by.
     */
    public function counting(): string
    {
        return $this->counting;
    }

    /**
     * The seats the count fills: 1 but for a poll counted by a method that fills several.
     */
    public function seats(): int
    {
        return $this->seats;
    }

    /**
     * Whether ballots are kept apart from their voters: who voted is known, not what they chose.
     */
    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function resultsVisibility(): ResultsVisibility
    {
        return $this->results;
    }

    public function subject(): ?ResourceReference
    {
        return null === $this->subject ? null : ResourceReference::fromString($this->subject);
    }

    public function createdBy(): ResourceReference
    {
        return ResourceReference::fromString($this->createdBy);
    }

    public function isCreatedBy(ResourceReference $actor): bool
    {
        return $this->createdBy === $actor->toString();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function opensAt(): ?DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function closesAt(): ?DateTimeImmutable
    {
        return $this->closesAt;
    }

    /**
     * When the poll was closed by hand, `null` while it was not.
     */
    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    /**
     * Whether ballots are accepted at this instant: opened, not yet at its closing, not closed by hand.
     */
    public function isOpenAt(DateTimeImmutable $now): bool
    {
        if ($this->opensAt instanceof DateTimeImmutable && $now->getTimestamp() < $this->opensAt->getTimestamp()) {
            return false;
        }

        return !$this->isClosedAt($now);
    }

    /**
     * Whether the poll is over at this instant: closed by hand, or at or past its closing.
     */
    public function isClosedAt(DateTimeImmutable $now): bool
    {
        return $this->closedAt instanceof DateTimeImmutable
            || ($this->closesAt instanceof DateTimeImmutable && $now->getTimestamp() >= $this->closesAt->getTimestamp());
    }

    /**
     * Closes the poll now; closing a poll already over changes nothing.
     */
    public function close(DateTimeImmutable $now): void
    {
        if (!$this->isClosedAt($now)) {
            $this->closedAt = self::second($now);
        }
    }

    /**
     * Sets when the poll opens and closes, `null` for at once and for never.
     *
     * @throws InvalidPollDefinition when it would close at or before it opens
     */
    public function reschedule(?DateTimeImmutable $opensAt, ?DateTimeImmutable $closesAt): void
    {
        if ($opensAt instanceof DateTimeImmutable && $closesAt instanceof DateTimeImmutable && $closesAt->getTimestamp() <= $opensAt->getTimestamp()) {
            throw InvalidPollDefinition::because('A poll closes after it opens.');
        }

        $this->opensAt = $opensAt instanceof DateTimeImmutable ? self::second($opensAt) : null;
        $this->closesAt = $closesAt instanceof DateTimeImmutable ? self::second($closesAt) : null;
    }

    private static function second(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$moment->getTimestamp());
    }
}
