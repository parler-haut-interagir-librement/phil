<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application;

use DateTimeImmutable;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;

/**
 * What a new poll is made of. `$maxChoices` is every option when `null`, `$counting` the default method of the kind
 * of ballot when `null`; a single ballot ignores both bounds. `$seats` above 1 asks for a method that fills several
 * seats, such as `meek_stv`.
 */
final readonly class PollDraft
{
    /**
     * @param list<PollOption> $options
     */
    public function __construct(
        public string $question,
        public array $options,
        public BallotKind $kind = BallotKind::Single,
        public int $minChoices = 1,
        public ?int $maxChoices = null,
        public bool $abstention = false,
        public ?string $counting = null,
        public bool $anonymous = false,
        public ResultsVisibility $results = ResultsVisibility::Always,
        public ?ResourceReference $subject = null,
        public ?DateTimeImmutable $opensAt = null,
        public ?DateTimeImmutable $closesAt = null,
        public int $seats = 1,
    ) {
    }

    /**
     * The rules of the ballots.
     *
     * @throws InvalidPoll when the options or the bounds break the rules of a poll
     */
    public function rules(): PollRules
    {
        $options = array_map(static fn (PollOption $option): string => $option->id, $this->options);

        return BallotKind::Single === $this->kind
            ? PollRules::single($options, $this->abstention)
            : PollRules::of($this->kind, $options, $this->minChoices, $this->maxChoices ?? \count($options), $this->abstention);
    }
}
