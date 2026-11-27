<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Import;

use DateTimeImmutable;
use Phil\SurveyBundle\Application\Exception\InvalidQuestionRow;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Choice;
use Phil\SurveyBundle\Domain\ValueObject\Question;

/**
 * A row of the CSV file of questions read as a question, cell by cell, the cell at fault named when it breaks a rule.
 *
 * The choices of a question, the columns of a matrix and its rows are separated by `|`. Each is written `value = label`
 * when the text before its first `=` is a value — 1 to 64 ASCII letters, digits, `_` or `-` — and as its label alone
 * otherwise, its value then its position: `1`, `2`, `3`…
 */
final class QuestionRows
{
    /** How many choices a question lists, at most. */
    public const int MAX_CHOICES = 100;

    private const string NAME = '/^[a-z][a-z0-9_]{0,63}$/D';

    private const string VALUED = '/^([A-Za-z0-9_-]{1,64})\s*=(.*)$/sD';

    private function __construct()
    {
    }

    /**
     * @param array<string, string|int|bool|DateTimeImmutable|null> $values    the row's values by column, null for an empty cell
     * @param array<string, mixed>|null                             $shownWhen the condition of the question the row replaces, which the file does not carry
     *
     * @throws InvalidQuestionRow when a cell breaks a rule of the question
     */
    public static function read(array $values, ?array $shownWhen = null): ImportedQuestion
    {
        $name = self::text($values, 'question_id');

        if (1 !== preg_match(self::NAME, $name)) {
            throw new InvalidQuestionRow('question_id', 'An identifier of a question is 1 to 64 lower-case letters, digits or "_", starting with a letter.');
        }

        $type = QuestionType::tryFrom(self::text($values, 'type')) ?? throw new InvalidQuestionRow('type', 'This is not a type of question.');
        $section = self::text($values, 'section');

        if ('' !== $section && 1 !== preg_match(self::NAME, $section)) {
            throw new InvalidQuestionRow('section', 'A name of a section is 1 to 64 lower-case letters, digits or "_", starting with a letter.');
        }

        $choices = self::choices(self::text($values, 'choices'), 'choices', self::MAX_CHOICES);
        $rows = self::choices(self::text($values, 'rows'), 'rows', Question::MAX_ROWS);
        self::checkLists($type, $choices, $rows);
        $min = self::integer($values, 'min');
        $max = self::integer($values, 'max');
        self::checkBounds($type, $min, $max);
        $maxLength = self::integer($values, 'max_length');
        self::checkLength($type, $maxLength);

        try {
            $question = new Question($name, self::text($values, 'label'), $type, true === ($values['required'] ?? null), self::text($values, 'help'), $choices, $rows, $min, $max, $maxLength, $shownWhen);
        } catch (InvalidQuestionnaire $invalidQuestionnaire) {
            throw new InvalidQuestionRow(match (true) {
                $type->hasChoices() => 'choices', QuestionType::Scale === $type => 'max', default => null,
            }, $invalidQuestionnaire->getMessage());
        }

        $title = self::text($values, 'section_title');

        return new ImportedQuestion($question, '' === $section ? null : $section, '' === $title ? null : $title);
    }

    /**
     * @return list<Choice>
     */
    private static function choices(string $cell, string $column, int $max): array
    {
        $items = array_values(array_filter(array_map(trim(...), explode('|', $cell)), static fn (string $item): bool => '' !== $item));

        if (\count($items) > $max) {
            throw new InvalidQuestionRow($column, \sprintf('A question lists at most %d %s.', $max, $column));
        }

        $choices = [];

        foreach ($items as $index => $item) {
            $value = (string) ($index + 1);
            $label = $item;

            if (1 === preg_match(self::VALUED, $item, $parts) && '' !== trim($parts[2])) {
                [, $value, $label] = $parts;
            }

            if (isset($choices[$value])) {
                throw new InvalidQuestionRow($column, \sprintf('Each of the %s has its own value.', $column));
            }

            if (mb_strlen(trim($label)) > Choice::MAX_LABEL) {
                throw new InvalidQuestionRow($column, \sprintf('A label of the %s is at most %d characters.', $column, Choice::MAX_LABEL));
            }

            $choices[$value] = new Choice($value, $label);
        }

        return array_values($choices);
    }

    /**
     * @param list<Choice> $choices
     * @param list<Choice> $rows
     */
    private static function checkLists(QuestionType $type, array $choices, array $rows): void
    {
        if ($type->hasChoices() === ([] === $choices)) {
            throw new InvalidQuestionRow('choices', $type->hasChoices() ? 'This type of question lists its choices.' : 'Only a single or multiple choice, a ranking or a matrix lists choices.');
        }

        if ((QuestionType::Matrix === $type) === ([] === $rows)) {
            throw new InvalidQuestionRow('rows', QuestionType::Matrix === $type ? 'A matrix lists its rows.' : 'Only a matrix lists rows.');
        }
    }

    private static function checkBounds(QuestionType $type, ?int $min, ?int $max): void
    {
        $bounded = \in_array($type, [QuestionType::Number, QuestionType::Scale, QuestionType::MultipleChoice, QuestionType::Ranking], true);

        foreach (['min' => $min, 'max' => $max] as $column => $bound) {
            if (null !== $bound && !$bounded) {
                throw new InvalidQuestionRow($column, 'Only a number, a scale, a multiple choice or a ranking has bounds.');
            }

            if (null === $bound && QuestionType::Scale === $type) {
                throw new InvalidQuestionRow($column, 'A scale has its lowest and its highest mark.');
            }

            if (null !== $bound && abs($bound) > Question::MAX_NUMBER) {
                throw new InvalidQuestionRow($column, \sprintf('A bound is at most %d away from zero.', Question::MAX_NUMBER));
            }
        }

        if (null !== $min && null !== $max && $min > $max) {
            throw new InvalidQuestionRow('max', 'The highest bound is not below the lowest.');
        }
    }

    private static function checkLength(QuestionType $type, ?int $maxLength): void
    {
        if (null === $maxLength) {
            return;
        }

        if (!\in_array($type, [QuestionType::Text, QuestionType::Textarea, QuestionType::Email], true)) {
            throw new InvalidQuestionRow('max_length', 'Only a free text has a length.');
        }

        if ($maxLength < 1 || $maxLength > 10_000) {
            throw new InvalidQuestionRow('max_length', 'A free text is 1 to 10000 characters long.');
        }
    }

    /**
     * @param array<string, string|int|bool|DateTimeImmutable|null> $values
     */
    private static function text(array $values, string $column): string
    {
        $value = $values[$column] ?? null;

        return \is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string, string|int|bool|DateTimeImmutable|null> $values
     */
    private static function integer(array $values, string $column): ?int
    {
        $value = $values[$column] ?? null;

        return \is_int($value) ? $value : null;
    }
}
