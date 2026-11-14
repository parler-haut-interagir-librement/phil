<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application;

use DateTimeImmutable;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Exception\InvalidPollDefinition;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;

/**
 * What a new poll is made of. `$maxChoices` is every option when `null`, `$counting` the default method of the kind
 * of ballot when `null`; a single ballot and a scale ignore both bounds. `$seats` above 1 asks for a method that fills
 * several seats, such as `meek_stv`. The options of a scale are its marks, which `marks()` writes: the count of a
 * scale gives their distribution and mean, and `nps` counts a scale from 0 to 10 as a Net Promoter Score.
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
     * The options of a scale: the marks from `$lowest` to `$highest`, each labelled by `$labels` under its mark, or by
     * the mark itself.
     *
     * @param array<int, string> $labels
     *
     * @return list<PollOption>
     *
     * @throws InvalidPoll           when the scale has fewer than 2 or more than 100 marks
     * @throws InvalidPollDefinition when a label is empty or too long
     */
    public static function marks(int $lowest, int $highest, array $labels = []): array
    {
        return array_map(
            static fn (string $mark): PollOption => PollOption::of($mark, $labels[(int) $mark] ?? $mark),
            PollRules::scale($lowest, $highest)->options,
        );
    }

    /**
     * The rules of the ballots.
     *
     * @throws InvalidPoll when the options or the bounds break the rules of a poll
     */
    public function rules(): PollRules
    {
        $options = array_map(static fn (PollOption $option): string => $option->id, $this->options);

        return match ($this->kind) {
            BallotKind::Single, BallotKind::Scale => PollRules::of($this->kind, $options, 1, 1, $this->abstention),
            BallotKind::Multiple, BallotKind::Ranked => PollRules::of($this->kind, $options, $this->minChoices, $this->maxChoices ?? \count($options), $this->abstention),
        };
    }
}
