<?php

declare(strict_types=1);

namespace Phil\Listings;

use Phil\Listings\Exception\InvalidListing;

/**
 * One criterion of a listing, or of a search: a name the application defines and a choice, a range or a flag.
 *
 * Two criteria meet when they have the same name and type and their values overlap: a choice shares at least one
 * value, a range shares at least one integer, a flag is the same. A choice has `choices` and no ends nor flag, a range
 * has `min` and `max`, a flag has `flag`; the fields a type does not use are empty.
 */
final readonly class Criterion
{
    public const int MAX_NAME_LENGTH = 64;

    public const int MAX_CHOICES = 32;

    public const int MAX_CHOICE_LENGTH = 64;

    private const string NAME = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D';

    private const string CHOICE = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D';

    /**
     * @param list<string> $choices
     */
    private function __construct(
        public string $name,
        public CriterionType $type,
        public array $choices,
        public ?int $min,
        public ?int $max,
        public ?bool $flag,
    ) {
    }

    /**
     * One or more values out of a set the application defines, kept once each in the order given.
     *
     * @param list<string> $choices
     *
     * @throws InvalidListing
     */
    public static function choice(string $name, array $choices): self
    {
        $kept = [];

        foreach ($choices as $choice) {
            if (\strlen($choice) > self::MAX_CHOICE_LENGTH || 1 !== preg_match(self::CHOICE, $choice)) {
                throw InvalidListing::because(\sprintf('A choice is 1 to %d ASCII letters, digits, ".", "_", ":" or "-".', self::MAX_CHOICE_LENGTH));
            }

            $kept[$choice] = $choice;
        }

        if ([] === $kept || \count($kept) > self::MAX_CHOICES) {
            throw InvalidListing::because(\sprintf('A choice criterion has 1 to %d distinct values.', self::MAX_CHOICES));
        }

        return new self(self::name($name), CriterionType::Choice, array_values($kept), null, null, null);
    }

    /**
     * An integer interval, both ends included.
     *
     * @throws InvalidListing
     */
    public static function range(string $name, int $min, int $max): self
    {
        if ($min > $max) {
            throw InvalidListing::because(\sprintf('A range runs from its minimum to its maximum; %d is above %d.', $min, $max));
        }

        return new self(self::name($name), CriterionType::Range, [], $min, $max, null);
    }

    /**
     * @throws InvalidListing
     */
    public static function flag(string $name, bool $flag): self
    {
        return new self(self::name($name), CriterionType::Flag, [], null, null, $flag);
    }

    public function meets(self $wanted): bool
    {
        if ($this->name !== $wanted->name || $this->type !== $wanted->type) {
            return false;
        }

        return match ($this->type) {
            CriterionType::Choice => [] !== array_intersect($this->choices, $wanted->choices),
            CriterionType::Range => $this->min <= $wanted->max && $wanted->min <= $this->max,
            CriterionType::Flag => $this->flag === $wanted->flag,
        };
    }

    private static function name(string $name): string
    {
        if (\strlen($name) > self::MAX_NAME_LENGTH || 1 !== preg_match(self::NAME, $name)) {
            throw InvalidListing::because(\sprintf('A criterion name is 1 to %d lowercase ASCII segments separated by ".", "_" or "-".', self::MAX_NAME_LENGTH));
        }

        return $name;
    }
}
