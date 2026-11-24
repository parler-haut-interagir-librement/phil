<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Logic\AnswerCheck;
use Phil\SurveyBundle\Domain\Model\Read;
use Phil\SurveyBundle\Domain\Status\QuestionType;

/**
 * A question: its name, the variable its conditions and the logic read, its label, its type and what bounds its
 * answer. A free answer is checked by a field of phil/forms-bundle; a choice, a mark, a ranking and each row of a
 * matrix by the rules of a poll of phil/poll, which also count them.
 *
 * - `choices` lists the options of a single or multiple choice and of a ranking, and the columns of a matrix: 2 to 100;
 * - `rows` lists the rows of a matrix, 1 to 50, each answered with one of its columns;
 * - `min` and `max` bound a number, the marks of a scale — required, 2 to 100 marks —, or how many options a multiple
 *   choice or a ranking holds, 1 to all by default;
 * - `max_length` bounds a free text, 1 to 10000 characters;
 * - `shown_when` is the condition that shows the question, which reads the questions before it of the types
 *   `QuestionType::isReadByConditions()` names.
 */
final readonly class Question
{
    public const int MAX_ROWS = 50;

    public const int MAX_HELP = 1000;

    public const int MAX_NUMBER = 999_999_999_999_999;

    private const array MEMBERS = ['name', 'label', 'type', 'required', 'help', 'choices', 'rows', 'min', 'max', 'max_length', 'shown_when'];

    public string $label;

    public string $help;

    /**
     * @param list<Choice>              $choices
     * @param list<Choice>              $rows
     * @param array<string, mixed>|null $shownWhen
     *
     * @throws InvalidQuestionnaire when a bound does not suit the type
     */
    public function __construct(
        public string $name,
        string $label,
        public QuestionType $type,
        public bool $required = false,
        string $help = '',
        public array $choices = [],
        public array $rows = [],
        public ?int $min = null,
        public ?int $max = null,
        public ?int $maxLength = null,
        public ?array $shownWhen = null,
    ) {
        Read::name($name, 'a question');
        $this->label = Read::text($label, 'the label of "'.$name.'"', Choice::MAX_LABEL);
        $this->help = Read::text($help, 'the help of "'.$name.'"', self::MAX_HELP, true);
        $this->checkChoices();
        $this->checkBounds();

        try {
            $this->field();
            $this->rules();
        } catch (InvalidForm|InvalidPoll $invalid) {
            throw InvalidQuestionnaire::because(\sprintf('The question "%s" is refused: %s', $name, $invalid->getMessage()));
        }
    }

    /**
     * @throws InvalidQuestionnaire when the question is not of the shape `toArray()` writes
     */
    public static function fromArray(mixed $data): self
    {
        $question = Read::map($data, 'a question', self::MEMBERS);
        $what = 'a question';
        $type = QuestionType::tryFrom(Read::string($question, 'type', $what))
            ?? throw InvalidQuestionnaire::because(\sprintf('The type of a question is one of: %s.', implode(', ', array_column(QuestionType::cases(), 'value'))));

        return new self(
            Read::string($question, 'name', $what),
            Read::string($question, 'label', $what),
            $type,
            Read::bool($question, 'required', $what, false),
            Read::optionalString($question, 'help', $what) ?? '',
            array_map(Choice::fromArray(...), Read::list($question, 'choices', $what)),
            array_map(Choice::fromArray(...), Read::list($question, 'rows', $what)),
            Read::optionalInt($question, 'min', $what),
            Read::optionalInt($question, 'max', $what),
            Read::optionalInt($question, 'max_length', $what),
            Read::condition($question, 'shown_when', $what),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'help' => '' === $this->help ? null : $this->help,
            'choices' => [] === $this->choices ? null : array_map(static fn (Choice $choice): array => $choice->toArray(), $this->choices),
            'rows' => [] === $this->rows ? null : array_map(static fn (Choice $row): array => $row->toArray(), $this->rows),
            'min' => $this->min,
            'max' => $this->max,
            'max_length' => $this->maxLength,
            'shown_when' => $this->shownWhen,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @return list<string> the values of the options, or of the columns of a matrix
     */
    public function values(): array
    {
        return array_map(static fn (Choice $choice): string => $choice->value, $this->choices);
    }

    /**
     * @return list<string> the values of the rows of a matrix
     */
    public function rowValues(): array
    {
        return array_map(static fn (Choice $row): string => $row->value, $this->rows);
    }

    /**
     * The field of phil/forms-bundle that checks a free answer, `null` for the other types.
     *
     * @throws InvalidForm never for a question built
     */
    public function field(): ?Field
    {
        $type = match ($this->type) {
            QuestionType::Text => FieldType::Text,
            QuestionType::Textarea => FieldType::Textarea,
            QuestionType::Email => FieldType::Email,
            QuestionType::Date => FieldType::Date,
            QuestionType::YesNo => FieldType::Checkbox,
            default => null,
        };

        return null === $type ? null : Field::of($this->name, $this->label, $type, $this->required, maxLength: $this->maxLength);
    }

    /**
     * The rules of the poll that checks and counts the answers — of each row, for a matrix —, `null` for a free answer
     * and a number. A yes or no is counted as a poll of `yes` and `no`.
     *
     * @throws InvalidPoll never for a question built
     */
    public function rules(): ?PollRules
    {
        return match ($this->type) {
            QuestionType::SingleChoice, QuestionType::Matrix => PollRules::single($this->values(), true),
            QuestionType::MultipleChoice => PollRules::multiple($this->values(), $this->min ?? 1, $this->max, true),
            QuestionType::Ranking => PollRules::ranked($this->values(), $this->min ?? 1, $this->max, true),
            QuestionType::Scale => PollRules::scale((int) $this->min, (int) $this->max, true),
            QuestionType::NetPromoter => PollRules::netPromoter(true),
            QuestionType::YesNo => PollRules::single(['yes', 'no'], true),
            default => null,
        };
    }

    /**
     * The value as the browser's logic controller collects it, which the conditions read on the server as in the
     * browser: a lone checkbox as true or false, a list as a list of texts, an empty text as `null`, a nested answer —
     * a ranking, a matrix — as it came.
     */
    public function collected(mixed $submitted): mixed
    {
        return match (true) {
            QuestionType::YesNo === $this->type => true === $submitted || '1' === $submitted,
            QuestionType::MultipleChoice === $this->type => \is_array($submitted) ? array_values(array_filter($submitted, \is_string(...))) : [],
            \is_string($submitted) => '' === trim($submitted) ? null : $submitted,
            \is_int($submitted), \is_float($submitted), \is_array($submitted) => $submitted,
            default => null,
        };
    }

    /**
     * Checks an answer and gives the value stored: a text, a number, a day `YYYY-MM-DD`, a boolean, an option, a list
     * of options — in order, for a ranking —, a mark, or the column of each row answered of a matrix; `null` when not
     * answered.
     *
     * @return array{value: string|int|float|bool|list<string>|array<string, string>|null, error: ?string}
     */
    public function accept(mixed $submitted): array
    {
        $field = $this->field();

        if ($field instanceof Field) {
            $accepted = $field->accept(\is_int($submitted) ? (string) $submitted : $submitted);

            if (QuestionType::YesNo === $this->type) {
                return ['value' => true === $accepted['value'], 'error' => $accepted['error']];
            }

            return null === $accepted['error'] ? $accepted : ['value' => null, 'error' => $accepted['error']];
        }

        $check = new AnswerCheck($this);

        return match ($this->type) {
            QuestionType::Number => $check->number($submitted),
            QuestionType::Matrix => $check->matrix($submitted),
            QuestionType::Ranking => $check->ranking($submitted),
            QuestionType::MultipleChoice => $check->ballot(\is_array($submitted) ? $submitted : (null === $submitted || '' === $submitted ? [] : null)),
            default => $check->mark($submitted),
        };
    }

    private function checkChoices(): void
    {
        $hasChoices = $this->type->hasChoices();

        if ($hasChoices === ([] === $this->choices)) {
            throw InvalidQuestionnaire::because(\sprintf('A question lists choices when it is a choice, a ranking or a matrix, and only then: "%s".', $this->name));
        }

        if ((QuestionType::Matrix === $this->type) === ([] === $this->rows) || \count($this->rows) > self::MAX_ROWS) {
            throw InvalidQuestionnaire::because(\sprintf('A matrix has 1 to %d rows, and only a matrix has rows: "%s".', self::MAX_ROWS, $this->name));
        }

        foreach ([$this->values(), $this->rowValues()] as $values) {
            if (\count($values) !== \count(array_unique($values))) {
                throw InvalidQuestionnaire::because(\sprintf('The choices and rows of "%s" are each given once.', $this->name));
            }
        }
    }

    private function checkBounds(): void
    {
        $bounded = \in_array($this->type, [QuestionType::Number, QuestionType::Scale, QuestionType::MultipleChoice, QuestionType::Ranking], true);

        if (!$bounded && (null !== $this->min || null !== $this->max)) {
            throw InvalidQuestionnaire::because(\sprintf('Only a number, a scale, a multiple choice or a ranking has bounds: "%s".', $this->name));
        }

        if (null !== $this->maxLength && !\in_array($this->type, [QuestionType::Text, QuestionType::Textarea, QuestionType::Email], true)) {
            throw InvalidQuestionnaire::because(\sprintf('Only a free text has a length: "%s".', $this->name));
        }

        if (QuestionType::Scale === $this->type && (null === $this->min || null === $this->max)) {
            throw InvalidQuestionnaire::because(\sprintf('A scale has its lowest and its highest mark: "%s".', $this->name));
        }

        foreach ([$this->min, $this->max] as $bound) {
            if (null !== $bound && abs($bound) > self::MAX_NUMBER) {
                throw InvalidQuestionnaire::because(\sprintf('A bound of "%s" is at most %d away from zero.', $this->name, self::MAX_NUMBER));
            }
        }

        if (null !== $this->min && null !== $this->max && $this->min > $this->max) {
            throw InvalidQuestionnaire::because(\sprintf('The lowest bound of "%s" is not above its highest.', $this->name));
        }
    }
}
