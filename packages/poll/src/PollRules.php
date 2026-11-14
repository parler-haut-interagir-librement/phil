<?php

declare(strict_types=1);

namespace Phil\Poll;

use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;

/**
 * What a ballot of one poll may hold: its kind, the identifiers of its options in their declared order, the minimum
 * and maximum of choices, and whether a blank ballot is an abstention or refused.
 *
 * An option is identified by 1 to 64 ASCII letters, digits, `_` or `-`; its label belongs to the application. The
 * options of a scale are its marks, whole numbers written in decimal from the lowest to the highest, each one more than
 * the one before: `0` to `10` for a Net Promoter Score. A poll has 2 to 100 options, which bounds the cost of a count: the pairwise count of Schulze grows with the square of the
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
     * One mark per ballot, from `$lowest` to `$highest`: the marks are the options, `"1"` to `"5"` for a scale of five.
     *
     * @throws InvalidPoll when the scale has fewer than 2 or more than 100 marks
     */
    public static function scale(int $lowest, int $highest, bool $abstention = false): self
    {
        if ($highest <= $lowest || $highest - $lowest >= self::MAX_OPTIONS) {
            throw InvalidPoll::because(\sprintf('A scale has 2 to %d marks, from its lowest to its highest; not %d to %d.', self::MAX_OPTIONS, $lowest, $highest));
        }

        return self::of(BallotKind::Scale, array_map(strval(...), range($lowest, $highest)), 1, 1, $abstention);
    }

    /**
     * The scale of a Net Promoter Score: one mark from 0 to 10.
     */
    public static function netPromoter(bool $abstention = false): self
    {
        return self::scale(0, 10, $abstention);
    }

    /**
     * The rules of any kind, as a store keeps them.
     *
     * @param list<string> $options
     *
     * @throws InvalidPoll when the options are not 2 to 100 distinct identifiers, the bounds not 1 ≤ min ≤ max ≤ options,
     *                     a single ballot or a scale is given other bounds than one choice, or the options of a scale are
     *                     not its marks in order
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

        if (BallotKind::Scale === $kind) {
            self::checkScale($options, $max);
        }

        return new self($kind, $options, $min, $max, $abstention);
    }

    /**
     * The mark an option of a scale stands for, `null` for an option the rules do not have or rules of another kind.
     */
    public function mark(string $option): ?int
    {
        return BallotKind::Scale === $this->kind && $this->has($option) ? (int) $option : null;
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

    /**
     * @param list<string> $options
     *
     * @throws InvalidPoll
     */
    private static function checkScale(array $options, int $max): void
    {
        if (1 !== $max) {
            throw InvalidPoll::because('A ballot on a scale holds exactly one mark.');
        }

        $lowest = $options[0];

        if (1 !== preg_match('/^(?:0|-?[1-9]\d{0,8})$/D', $lowest)) {
            throw InvalidPoll::because(\sprintf('The marks of a scale are whole numbers, not "%s".', $lowest));
        }

        $marks = array_map(strval(...), range((int) $lowest, (int) $lowest + \count($options) - 1));

        if ($marks !== $options) {
            throw InvalidPoll::because(\sprintf('The options of a scale are its marks in order, from %s to %s.', $lowest, $marks[\count($marks) - 1]));
        }
    }
}
