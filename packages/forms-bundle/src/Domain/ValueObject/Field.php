<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;

/**
 * A field of a form: its name in the entry, its label, its type, whether it is required, its choices, and the longest
 * text it takes; then, when it has them, the condition under which it is asked, the tiers it is asked for, the
 * choices that disqualify an entry with their message, the capacity of its choices, whether its value is
 * sensitive, and for a birth date the ages it takes and the day they are counted on.
 *
 * @phpstan-type FieldArray array{
 *     name: string,
 *     label: string,
 *     type: string,
 *     required: bool,
 *     choices: list<string>,
 *     max_length: ?int,
 *     shown_when?: array<array-key, mixed>,
 *     tiers?: list<string>,
 *     disqualifying?: array<array-key, string>,
 *     capacities?: array<array-key, int>,
 *     sensitive?: bool,
 *     min_age?: int,
 *     max_age?: int,
 *     reference_date?: string,
 * }
 */
final readonly class Field
{
    /** The most choices a field has. */
    public const int MAX_CHOICES = 500;

    /** The most places a choice holds. */
    public const int MAX_CAPACITY = 1_000_000;

    /** The oldest a birth date is, in years. */
    public const int MAX_AGE = 130;

    /**
     * @param list<string>                 $choices
     * @param array<array-key, mixed>|null $shownWhen
     * @param list<string>                 $tiers
     * @param array<array-key, string>     $disqualifying the message of each choice that disqualifies an entry
     * @param array<array-key, int>        $capacities    the places of each choice that has a capacity
     */
    private function __construct(
        public string $name,
        public string $label,
        public FieldType $type,
        public bool $required,
        public array $choices,
        public ?int $maxLength,
        public ?array $shownWhen,
        public array $tiers,
        public array $disqualifying,
        public array $capacities,
        public bool $sensitive,
        public ?int $minAge,
        public ?int $maxAge,
        public ?string $referenceDate,
    ) {
    }

    /**
     * @param list<string>                 $choices       each 1 to 255 characters, at most 500
     * @param array<array-key, mixed>|null $shownWhen     the condition under which the field is asked, read by the
     *                                                    form's `ConditionEvaluator` when the form is defined
     * @param list<string>                 $tiers         the tiers it is asked for — a kind of ticket, a membership —;
     *                                                    none: every entry
     * @param array<array-key, string>     $disqualifying by choice, the message of an entry it disqualifies
     * @param array<array-key, int>        $capacities    by choice, how many entries may keep it, 1 to 1000000
     * @param bool                         $sensitive     whether the value is sealed by the `FieldSealer`; it is never
     *                                                    sent by email or webhook
     * @param int|null                     $minAge        for a birth date, the youngest age taken, in full years, 0
     *                                                    to 130: a person of that age is accepted
     * @param int|null                     $maxAge        for a birth date, the oldest age taken, in full years, 0 to
     *                                                    130, not under `$minAge`: a person of that age is accepted
     * @param string|null                  $referenceDate for a birth date with an age, the day the age is counted on,
     *                                                    `YYYY-MM-DD`; null: the day the entry comes
     */
    public static function of(
        string $name,
        string $label,
        FieldType $type,
        bool $required = false,
        array $choices = [],
        ?int $maxLength = null,
        ?array $shownWhen = null,
        array $tiers = [],
        array $disqualifying = [],
        array $capacities = [],
        bool $sensitive = false,
        ?int $minAge = null,
        ?int $maxAge = null,
        ?string $referenceDate = null,
    ): self {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name)) {
            throw InvalidForm::because(\sprintf('A field name is 1 to 64 lower-case letters, digits or "_", starting with a letter; "%s" is not.', $name));
        }

        $label = trim($label);

        if ('' === $label || mb_strlen($label) > 255) {
            throw InvalidForm::because(\sprintf('The label of "%s" is 1 to 255 characters.', $name));
        }

        self::checkChoices($name, $type, $choices);
        $default = $type->defaultMaxLength();

        if (null !== $maxLength && (null === $default || $maxLength < 1 || $maxLength > 10_000)) {
            throw InvalidForm::because(\sprintf('A text field takes 1 to 10000 characters, and only a text field has a length: "%s".', $name));
        }

        self::checkTiers($name, $tiers);
        self::checkDisqualifying($name, $choices, $disqualifying);
        self::checkCapacities($name, $choices, $capacities);

        if ($sensitive && (FieldType::Checkbox === $type || [] !== $capacities)) {
            throw InvalidForm::because(\sprintf('A sensitive field is neither a checkbox nor a choice with places: "%s".', $name));
        }

        self::checkAges($name, $type, $minAge, $maxAge, $referenceDate);

        return new self($name, $label, $type, $required, $choices, $maxLength ?? $default, $shownWhen, $tiers, $disqualifying, $capacities, $sensitive, $minAge, $maxAge, $referenceDate);
    }

    /**
     * @param FieldArray $field
     */
    public static function fromArray(array $field): self
    {
        return self::of(
            $field['name'],
            $field['label'],
            FieldType::from($field['type']),
            $field['required'],
            $field['choices'],
            $field['max_length'],
            $field['shown_when'] ?? null,
            $field['tiers'] ?? [],
            $field['disqualifying'] ?? [],
            $field['capacities'] ?? [],
            $field['sensitive'] ?? false,
            $field['min_age'] ?? null,
            $field['max_age'] ?? null,
            $field['reference_date'] ?? null,
        );
    }

    /**
     * The definition as kept in the form's row: what the field does not use is left out.
     *
     * @return FieldArray
     */
    public function toArray(): array
    {
        $field = ['name' => $this->name, 'label' => $this->label, 'type' => $this->type->value, 'required' => $this->required, 'choices' => $this->choices, 'max_length' => $this->maxLength];

        if (null !== $this->shownWhen) {
            $field['shown_when'] = $this->shownWhen;
        }

        if ([] !== $this->tiers) {
            $field['tiers'] = $this->tiers;
        }

        if ([] !== $this->disqualifying) {
            $field['disqualifying'] = $this->disqualifying;
        }

        if ([] !== $this->capacities) {
            $field['capacities'] = $this->capacities;
        }

        if ($this->sensitive) {
            $field['sensitive'] = true;
        }

        if (null !== $this->minAge) {
            $field['min_age'] = $this->minAge;
        }

        if (null !== $this->maxAge) {
            $field['max_age'] = $this->maxAge;
        }

        if (null !== $this->referenceDate) {
            $field['reference_date'] = $this->referenceDate;
        }

        return $field;
    }

    /**
     * Whether an entry for these tiers asks the field: a field without tiers is asked of every entry, one with tiers
     * of an entry for one of them.
     *
     * @param list<string> $tiers
     */
    public function isFor(array $tiers): bool
    {
        return [] === $this->tiers || [] !== array_intersect($this->tiers, $tiers);
    }

    /**
     * The message of an entry this value disqualifies, or null.
     */
    public function disqualification(string|bool|null $value): ?string
    {
        return \is_string($value) ? $this->disqualifying[$value] ?? null : null;
    }

    /**
     * The places of the choice, or null when it has no capacity.
     */
    public function capacity(string|bool|null $value): ?int
    {
        return \is_string($value) ? $this->capacities[$value] ?? null : null;
    }

    /**
     * The parameters of the message of an error of this field: the age of `too_young` and `too_old`.
     *
     * @return array<string, int>
     */
    public function errorParameters(string $error): array
    {
        return match ($error) {
            'too_young' => null === $this->minAge ? [] : ['%age%' => $this->minAge],
            'too_old' => null === $this->maxAge ? [] : ['%age%' => $this->maxAge],
            default => [],
        };
    }

    /**
     * The age in full years, on `$on`, of a person born on `$born`, both `YYYY-MM-DD`: a year is complete on the
     * birthday, and a person born on 29 February completes it on 1 March in a year without that day. Negative when
     * `$on` comes before the birth.
     */
    public static function age(string $born, string $on): int
    {
        $years = (int) substr($on, 0, 4) - (int) substr($born, 0, 4);

        // Months and days written `MM-DD` compare as text in the order of the year.
        return strcmp(substr($on, 5), substr($born, 5)) < 0 ? $years - 1 : $years;
    }

    /**
     * The value kept for what was submitted, or the reason it is refused. A birth date is refused after `$today`, or
     * more than 130 years before it; with ages, `too_young` or `too_old` on the reference date, `$today` without one.
     *
     * @return array{value: string|bool|null, error: ?string}
     */
    public function accept(mixed $submitted, ?DateTimeImmutable $today = null): array
    {
        if (FieldType::Checkbox === $this->type) {
            $ticked = true === $submitted || '1' === $submitted;

            return ['value' => $ticked, 'error' => $this->required && !$ticked ? 'required' : null];
        }

        if (null !== $submitted && !\is_string($submitted)) {
            return ['value' => null, 'error' => 'type'];
        }

        $value = trim((string) $submitted);

        if ('' === $value) {
            return ['value' => null, 'error' => $this->required ? 'required' : null];
        }

        return ['value' => $value, 'error' => $this->problem($value, $today)];
    }

    private function problem(string $value, ?DateTimeImmutable $today): ?string
    {
        return match (true) {
            null !== $this->maxLength && mb_strlen($value) > $this->maxLength => 'too_long',
            FieldType::Email === $this->type && false === filter_var($value, \FILTER_VALIDATE_EMAIL) => 'email',
            FieldType::Url === $this->type && 1 !== preg_match('{^https?://[^\s/]+\S*$}iD', $value) => 'url',
            (FieldType::Date === $this->type || FieldType::BirthDate === $this->type) && !self::isDate($value) => 'date',
            FieldType::BirthDate === $this->type && !$this->isBirthDate($value, $today) => 'birth_date',
            FieldType::BirthDate === $this->type => $this->ageProblem($value, $today),
            FieldType::Choice === $this->type && !\in_array($value, $this->choices, true) => 'choice',
            null !== $this->disqualification($value) => 'disqualified',
            default => null,
        };
    }

    private static function isDate(string $value): bool
    {
        return 1 === preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/D', $value, $date) && checkdate((int) $date['month'], (int) $date['day'], (int) $date['year']);
    }

    /**
     * `too_young` or `too_old` on the reference date, or on `$today` without one; nothing to check without either.
     */
    private function ageProblem(string $value, ?DateTimeImmutable $today): ?string
    {
        $on = $this->referenceDate ?? $today?->format('Y-m-d');

        if (null === $on) {
            return null;
        }

        $age = self::age($value, $on);

        return match (true) {
            null !== $this->minAge && $age < $this->minAge => 'too_young',
            null !== $this->maxAge && $age > $this->maxAge => 'too_old',
            default => null,
        };
    }

    /**
     * Dates written `YYYY-MM-DD` compare as text in the order of time.
     */
    private function isBirthDate(string $value, ?DateTimeImmutable $today): bool
    {
        if (!$today instanceof DateTimeImmutable) {
            return true;
        }

        $earliest = $today->modify(\sprintf('-%d years', self::MAX_AGE))->format('Y-m-d');

        return strcmp($value, $today->format('Y-m-d')) <= 0 && strcmp($value, $earliest) >= 0;
    }

    /**
     * @param list<string> $choices
     */
    private static function checkChoices(string $name, FieldType $type, array $choices): void
    {
        if ((FieldType::Choice === $type) === ([] === $choices)) {
            throw InvalidForm::because(\sprintf('A choice field has choices, and only a choice field: "%s".', $name));
        }

        if (\count($choices) !== \count(array_unique($choices))) {
            throw InvalidForm::because(\sprintf('The choices of "%s" are each given once.', $name));
        }

        if (\count($choices) > self::MAX_CHOICES) {
            throw InvalidForm::because(\sprintf('A field has at most %d choices: "%s".', self::MAX_CHOICES, $name));
        }

        foreach ($choices as $choice) {
            if ('' === trim($choice) || mb_strlen($choice) > 255) {
                throw InvalidForm::because(\sprintf('A choice of "%s" is 1 to 255 characters.', $name));
            }
        }
    }

    private static function checkAges(string $name, FieldType $type, ?int $minAge, ?int $maxAge, ?string $referenceDate): void
    {
        if (null === $minAge && null === $maxAge && null === $referenceDate) {
            return;
        }

        if (FieldType::BirthDate !== $type) {
            throw InvalidForm::because(\sprintf('Only a birth date has ages: "%s".', $name));
        }

        foreach ([$minAge, $maxAge] as $age) {
            if (null !== $age && ($age < 0 || $age > self::MAX_AGE)) {
                throw InvalidForm::because(\sprintf('An age of "%s" is 0 to %d years.', $name, self::MAX_AGE));
            }
        }

        if (null !== $minAge && null !== $maxAge && $minAge > $maxAge) {
            throw InvalidForm::because(\sprintf('The youngest age of "%s" is not above its oldest: %d > %d.', $name, $minAge, $maxAge));
        }

        if (null === $minAge && null === $maxAge) {
            throw InvalidForm::because(\sprintf('A reference date goes with an age: "%s".', $name));
        }

        if (null !== $referenceDate && !self::isDate($referenceDate)) {
            throw InvalidForm::because(\sprintf('The reference date of "%s" is a date, YYYY-MM-DD; "%s" is not.', $name, $referenceDate));
        }
    }

    /**
     * @param list<string> $tiers
     */
    private static function checkTiers(string $name, array $tiers): void
    {
        foreach ($tiers as $tier) {
            if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $tier)) {
                throw InvalidForm::because(\sprintf('A tier of "%s" is 1 to 64 lower-case letters, digits, "_" or "-"; "%s" is not.', $name, $tier));
            }
        }

        if (\count($tiers) !== \count(array_unique($tiers))) {
            throw InvalidForm::because(\sprintf('The tiers of "%s" are each given once.', $name));
        }
    }

    /**
     * @param list<string>             $choices
     * @param array<array-key, string> $disqualifying
     */
    private static function checkDisqualifying(string $name, array $choices, array $disqualifying): void
    {
        foreach ($disqualifying as $choice => $message) {
            if (!\in_array((string) $choice, $choices, true)) {
                throw InvalidForm::because(\sprintf('Only a choice of "%s" disqualifies an entry; "%s" is not one.', $name, $choice));
            }

            if ('' === trim($message) || mb_strlen($message) > 500) {
                throw InvalidForm::because(\sprintf('The message of an entry "%s" disqualifies is 1 to 500 characters.', $name));
            }
        }
    }

    /**
     * @param list<string>          $choices
     * @param array<array-key, int> $capacities
     */
    private static function checkCapacities(string $name, array $choices, array $capacities): void
    {
        foreach ($capacities as $choice => $capacity) {
            if (!\in_array((string) $choice, $choices, true)) {
                throw InvalidForm::because(\sprintf('Only a choice of "%s" has a capacity; "%s" is not one.', $name, $choice));
            }

            if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
                throw InvalidForm::because(\sprintf('A choice of "%s" holds 1 to %d places.', $name, self::MAX_CAPACITY));
            }
        }
    }
}
