<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Logic;

use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\PollRules;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Question;

/**
 * Checks the answers phil/forms-bundle does not: a number within its bounds, a mark or an option, a ballot of options
 * — checked by the question's poll rules —, a ranking given in order or rank by rank, and the column of each row of a
 * matrix. Each gives the value stored and the error, `null` when the answer is accepted.
 *
 * @internal
 */
final readonly class AnswerCheck
{
    private const string NUMBER = '/^-?(?:0|[1-9]\d{0,14})(?:\.\d{1,6})?$/D';

    public function __construct(private Question $question)
    {
    }

    /**
     * @return array{value: int|float|null, error: ?string}
     */
    public function number(mixed $submitted): array
    {
        $text = \is_int($submitted) ? (string) $submitted : $submitted;

        if (null === $text || '' === $text) {
            return ['value' => null, 'error' => $this->question->required ? 'required' : null];
        }

        if (!\is_string($text) || 1 !== preg_match(self::NUMBER, trim($text))) {
            return ['value' => null, 'error' => 'type'];
        }

        $number = str_contains($text, '.') ? (float) $text : (int) $text;

        if ((null !== $this->question->min && $number < $this->question->min) || (null !== $this->question->max && $number > $this->question->max)) {
            return ['value' => null, 'error' => 'range'];
        }

        return ['value' => $number, 'error' => null];
    }

    /**
     * @return array{value: int|string|null, error: ?string}
     */
    public function mark(mixed $submitted): array
    {
        $text = \is_int($submitted) ? (string) $submitted : $submitted;

        if (null === $text || '' === $text) {
            return ['value' => null, 'error' => $this->question->required ? 'required' : null];
        }

        if (!\is_string($text)) {
            return ['value' => null, 'error' => 'type'];
        }

        $rules = $this->question->rules();

        if (!$rules instanceof PollRules || !$rules->has($text)) {
            return ['value' => null, 'error' => QuestionType::SingleChoice === $this->question->type ? 'choice' : 'range'];
        }

        return ['value' => $rules->mark($text) ?? $text, 'error' => null];
    }

    /**
     * @param array<array-key, mixed>|null $choices
     *
     * @return array{value: list<string>|null, error: ?string}
     */
    public function ballot(?array $choices): array
    {
        if (null === $choices || !array_is_list($choices)) {
            return ['value' => null, 'error' => 'type'];
        }

        if ([] === $choices) {
            return ['value' => null, 'error' => $this->question->required ? 'required' : null];
        }

        try {
            /** @var list<string> $held */
            $held = $this->question->rules()?->ballot($choices)->choices ?? [];

            return ['value' => $held, 'error' => null];
        } catch (InvalidBallot) {
            return ['value' => null, 'error' => $this->countError($choices)];
        }
    }

    /**
     * A ranking comes as the options in order, or, from a form without JavaScript, as the rank of each option — `1`
     * first —, the options left blank not ranked.
     *
     * @return array{value: list<string>|null, error: ?string}
     */
    public function ranking(mixed $submitted): array
    {
        if (!\is_array($submitted) || array_is_list($submitted)) {
            return $this->ballot(\is_array($submitted) ? $submitted : (null === $submitted || '' === $submitted ? [] : null));
        }

        $ranks = [];

        foreach ($submitted as $option => $rank) {
            if (null === $rank || '' === $rank) {
                continue;
            }

            if (!\is_string($rank) || 1 !== preg_match('/^[1-9]\d{0,2}$/D', $rank) || isset($ranks[(int) $rank])) {
                return ['value' => null, 'error' => 'type'];
            }

            $ranks[(int) $rank] = (string) $option;
        }

        ksort($ranks);

        return $this->ballot(array_values($ranks));
    }

    /**
     * @return array{value: array<string, string>|null, error: ?string}
     */
    public function matrix(mixed $submitted): array
    {
        if (null === $submitted || '' === $submitted) {
            $submitted = [];
        }

        if (!\is_array($submitted)) {
            return ['value' => null, 'error' => 'type'];
        }

        if ([] !== array_diff_key($submitted, array_flip($this->question->rowValues()))) {
            return ['value' => null, 'error' => 'choice'];
        }

        $columns = $this->question->values();
        $answered = [];

        foreach ($this->question->rowValues() as $row) {
            $column = $submitted[$row] ?? null;

            if (null === $column || '' === $column) {
                continue;
            }

            if (!\is_string($column) || !\in_array($column, $columns, true)) {
                return ['value' => null, 'error' => 'choice'];
            }

            $answered[$row] = $column;
        }

        if ($this->question->required && \count($answered) < \count($this->question->rows)) {
            return ['value' => null, 'error' => 'required'];
        }

        return ['value' => [] === $answered ? null : $answered, 'error' => null];
    }

    /**
     * @param list<mixed> $choices
     */
    private function countError(array $choices): string
    {
        $known = $this->question->values();

        foreach ($choices as $choice) {
            if (!\is_string($choice) || !\in_array($choice, $known, true)) {
                return 'choice';
            }
        }

        if (\count($choices) !== \count(array_unique($choices))) {
            return 'choice';
        }

        return \count($choices) < ($this->question->min ?? 1) ? 'too_few' : 'too_many';
    }
}
