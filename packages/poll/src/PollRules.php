<?php

declare(strict_types=1);

namespace Phil\Poll;

use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;

/**
 * What a ballot of one poll may hold: its kind, the identifiers of its options in their declared order, the minimum
 * and maximum of choices, and whether a blank ballot is an abstention or refused.
 *
 * An option is identified by 1 to 64 ASCII letters, digits, `_` or `-`; its label belongs to the application. A poll
 * has 2 to 100 options, which bounds the cost of a count: the pairwise count of Schulze grows with the square of the
 * options, its strongest paths with their cube.
 */
final readonly class PollRules
{
    public const int MAX_OPTIONS = 100;

    public const int MAX_OPTION_LENGTH = 64;

    /**
     * @param list<string> $options
     */
    private function __construct(
        public BallotKind $kind,
        public array $options,
        public int $minChoices,
        public int $maxChoices,
        public bool $abstention,
    ) {
    }

    /**
     * One option per ballot.
     *
     * @param list<string> $options
     *
     * @throws InvalidPoll when the options are not 2 to 100 distinct identifiers
     */
    public static function single(array $options, bool $abstention = false): self
    {
        return self::of(BallotKind::Single, $options, 1, 1, $abstention);
    }

    /**
     * Between `$min` and `$max` options per ballot, every option when `$max` is `null`.
     *
     * @param list<string> $options
     *
     * @throws InvalidPoll when the options are not 2 to 100 distinct identifiers, or the bounds not 1 ≤ min ≤ max ≤ options
     */
    public static function multiple(array $options, int $min = 1, ?int $max = null, bool $abstention = false): self
    {
        return self::of(BallotKind::Multiple, $options, $min, $max ?? \count($options), $abstention);
    }

    /**
     * Between `$min` and `$max` options per ballot, ranked; every option may be ranked when `$max` is `null`.
     *
     * @param list<string> $options
     *
     * @throws InvalidPoll when the options are not 2 to 100 distinct identifiers, or the bounds not 1 ≤ min ≤ max ≤ options
     */
    public static function ranked(array $options, int $min = 1, ?int $max = null, bool $abstention = false): self
    {
        return self::of(BallotKind::Ranked, $options, $min, $max ?? \count($options), $abstention);
    }

    /**
     * The rules of any kind, as a store keeps them.
     *
     * @param list<string> $options
     *
     * @throws InvalidPoll when the options are not 2 to 100 distinct identifiers, the bounds not 1 ≤ min ≤ max ≤ options,
     *                     or a single ballot is given other bounds than one choice
     */
    public static function of(BallotKind $kind, array $options, int $min, int $max, bool $abstention = false): self
    {
        if (\count($options) < 2 || \count($options) > self::MAX_OPTIONS) {
            throw InvalidPoll::because(\sprintf('A poll has 2 to %d options, not %d.', self::MAX_OPTIONS, \count($options)));
        }

        $seen = [];

        foreach ($options as $option) {
            if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $option)) {
                throw InvalidPoll::because(\sprintf('An option is identified by 1 to %d ASCII letters, digits, "_" or "-", not "%s".', self::MAX_OPTION_LENGTH, $option));
            }

            if (isset($seen[$option])) {
                throw InvalidPoll::because(\sprintf('The option "%s" is declared twice.', $option));
            }

            $seen[$option] = $option;
        }

        if ($min < 1 || $max < $min || $max > \count($options)) {
            throw InvalidPoll::because(\sprintf('A ballot holds at least 1 choice and at most the %d options, the minimum not above the maximum; not %d to %d.', \count($options), $min, $max));
        }

        if (BallotKind::Single === $kind && 1 !== $max) {
            throw InvalidPoll::because('A single ballot holds exactly one choice.');
        }

        return new self($kind, $options, $min, $max, $abstention);
    }

    /**
     * Whether the option is one of the poll's.
     */
    public function has(string $option): bool
    {
        return \in_array($option, $this->options, true);
    }

    /**
     * The ballot of these choices, checked against the rules: values as a form or a request gives them, in the
     * voter's order of preference for a ranked ballot. An empty list is an abstention when the rules accept one.
     *
     * @param array<mixed> $choices
     *
     * @throws InvalidBallot when the choices are not a list of the poll's options, each once, within the bounds
     */
    public function ballot(array $choices): Ballot
    {
        if (!array_is_list($choices)) {
            throw InvalidBallot::because('The choices of a ballot are a list.');
        }

        if ([] === $choices) {
            if ($this->abstention) {
                return Ballot::of([]);
            }

            throw InvalidBallot::because('This poll accepts no blank ballot.');
        }

        $known = array_flip($this->options);
        $seen = [];

        foreach ($choices as $choice) {
            if (!\is_string($choice)) {
                throw InvalidBallot::because(\sprintf('A choice is the identifier of an option, not %s.', get_debug_type($choice)));
            }

            if (!isset($known[$choice])) {
                throw InvalidBallot::because(\sprintf('"%s" is not an option of this poll.', $choice));
            }

            if (isset($seen[$choice])) {
                throw InvalidBallot::because(\sprintf('The option "%s" is chosen twice.', $choice));
            }

            $seen[$choice] = $choice;
        }

        if (\count($choices) < $this->minChoices || \count($choices) > $this->maxChoices) {
            throw InvalidBallot::because($this->minChoices === $this->maxChoices ? \sprintf('A ballot of this poll holds %d choice(s), not %d.', $this->minChoices, \count($choices)) : \sprintf('A ballot of this poll holds %d to %d choices, not %d.', $this->minChoices, $this->maxChoices, \count($choices)));
        }

        /** @var list<string> $choices */
        return Ballot::of($choices);
    }
}
