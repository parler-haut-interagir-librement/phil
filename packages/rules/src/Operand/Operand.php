<?php

declare(strict_types=1);

namespace Phil\Rules\Operand;

use DateTimeImmutable;
use Phil\Rules\Exception\InvalidConditionType;
use Phil\Rules\Exception\InvalidLimit;

/**
 * One operand of a condition type: its name, its kind, whether a rule must give it, and its bounds. Built by the named
 * constructors, which check the bounds.
 */
final readonly class Operand
{
    public const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,127}$/D';

    public const int MAX_LENGTH = 10000;

    public const int MAX_ITEMS = 1000;

    /**
     * @param list<string> $choices
     */
    private function __construct(
        public string $name,
        public OperandKind $kind,
        public bool $required,
        public array $choices = [],
        public int $maxLength = 255,
        public int $maxItems = 100,
        public ?int $min = null,
        public ?int $max = null,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name)) {
            throw InvalidConditionType::operandName($name);
        }

        $this->bound('maxLength', $maxLength, self::MAX_LENGTH);
        $this->bound('maxItems', $maxItems, self::MAX_ITEMS);

        if (null !== $min && null !== $max && $min > $max) {
            throw InvalidLimit::outOfRange('max', $max, $min, \PHP_INT_MAX);
        }
    }

    public static function text(string $name, bool $required = true, int $maxLength = 255): self
    {
        return new self($name, OperandKind::Text, $required, maxLength: $maxLength);
    }

    public static function identifier(string $name, bool $required = true): self
    {
        return new self($name, OperandKind::Identifier, $required);
    }

    public static function integer(string $name, bool $required = true, ?int $min = null, ?int $max = null): self
    {
        return new self($name, OperandKind::Integer, $required, min: $min, max: $max);
    }

    public static function number(string $name, bool $required = true): self
    {
        return new self($name, OperandKind::Number, $required);
    }

    public static function boolean(string $name, bool $required = true): self
    {
        return new self($name, OperandKind::Boolean, $required);
    }

    /**
     * @param list<string> $choices
     */
    public static function choice(string $name, array $choices, bool $required = true): self
    {
        if ([] === $choices || \in_array('', $choices, true) || \count(array_unique($choices)) !== \count($choices)) {
            throw InvalidConditionType::choices($name);
        }

        return new self($name, OperandKind::Choice, $required, $choices);
    }

    public static function date(string $name, bool $required = true): self
    {
        return new self($name, OperandKind::Date, $required);
    }

    public static function scalar(string $name, bool $required = true, int $maxLength = 255): self
    {
        return new self($name, OperandKind::Scalar, $required, maxLength: $maxLength);
    }

    public static function identifierList(string $name, bool $required = true, int $maxItems = 100): self
    {
        return new self($name, OperandKind::IdentifierList, $required, maxItems: $maxItems);
    }

    public static function scalarList(string $name, bool $required = true, int $maxItems = 100, int $maxLength = 255): self
    {
        return new self($name, OperandKind::ScalarList, $required, maxLength: $maxLength, maxItems: $maxItems);
    }

    /**
     * The value as the operand keeps it, or the reason it is refused, worded to follow the operand's name.
     *
     * @return array{0: bool, 1: mixed}
     */
    public function accept(mixed $value): array
    {
        return match ($this->kind) {
            OperandKind::Text => $this->acceptText($value),
            OperandKind::Identifier => self::acceptIdentifier($value),
            OperandKind::Integer => $this->acceptInteger($value),
            OperandKind::Number => $this->acceptNumber($value),
            OperandKind::Boolean => \is_bool($value) ? [true, $value] : [false, 'must be true or false'],
            OperandKind::Choice => \is_string($value) && \in_array($value, $this->choices, true)
                ? [true, $value]
                : [false, \sprintf('must be one of "%s"', implode('", "', $this->choices))],
            OperandKind::Date => $this->acceptDate($value),
            OperandKind::Scalar => $this->acceptScalar($value),
            OperandKind::IdentifierList => $this->acceptList($value, static fn (mixed $item): array => self::acceptIdentifier($item)),
            OperandKind::ScalarList => $this->acceptList($value, $this->acceptScalar(...)),
        };
    }

    /**
     * What a rule builder needs to offer the operand.
     *
     * @return array{name: string, kind: string, required: bool, choices?: list<string>, maxLength?: int, maxItems?: int, min?: int, max?: int}
     */
    public function describe(): array
    {
        $description = ['name' => $this->name, 'kind' => $this->kind->value, 'required' => $this->required];

        if (OperandKind::Choice === $this->kind) {
            $description['choices'] = $this->choices;
        }

        if (\in_array($this->kind, [OperandKind::Text, OperandKind::Scalar, OperandKind::ScalarList], true)) {
            $description['maxLength'] = $this->maxLength;
        }

        if (\in_array($this->kind, [OperandKind::IdentifierList, OperandKind::ScalarList], true)) {
            $description['maxItems'] = $this->maxItems;
        }

        if (null !== $this->min) {
            $description['min'] = $this->min;
        }

        if (null !== $this->max) {
            $description['max'] = $this->max;
        }

        return $description;
    }

    private function bound(string $limit, int $value, int $max): void
    {
        if ($value < 1 || $value > $max) {
            throw InvalidLimit::outOfRange($limit, $value, 1, $max);
        }
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function acceptText(mixed $value): array
    {
        if (!\is_string($value)) {
            return [false, 'must be a text'];
        }

        if (\strlen($value) > $this->maxLength) {
            return [false, \sprintf('holds more than %d bytes', $this->maxLength)];
        }

        if (1 !== preg_match('/^[^\x00-\x1F\x7F]*$/Du', $value)) {
            return [false, 'must be UTF-8 without control characters'];
        }

        return [true, $value];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private static function acceptIdentifier(mixed $value): array
    {
        return \is_string($value) && 1 === preg_match(self::IDENTIFIER_PATTERN, $value)
            ? [true, $value]
            : [false, 'must be an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most'];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function acceptInteger(mixed $value): array
    {
        if (!\is_int($value)) {
            return [false, 'must be an integer'];
        }

        if (null !== $this->min && $value < $this->min) {
            return [false, \sprintf('must be %d or more', $this->min)];
        }

        if (null !== $this->max && $value > $this->max) {
            return [false, \sprintf('must be %d or less', $this->max)];
        }

        return [true, $value];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function acceptNumber(mixed $value): array
    {
        return (\is_int($value) || \is_float($value)) && is_finite((float) $value)
            ? [true, (float) $value]
            : [false, 'must be a finite number'];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function acceptDate(mixed $value): array
    {
        // Written back as it was read, the date is a calendar date of the form YYYY-MM-DD and nothing else.
        if (\is_string($value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            if (false !== $date && $date->format('Y-m-d') === $value) {
                return [true, $value];
            }
        }

        return [false, 'must be a calendar date written YYYY-MM-DD'];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function acceptScalar(mixed $value): array
    {
        if (\is_string($value)) {
            return $this->acceptText($value);
        }

        if (\is_float($value) && !is_finite($value)) {
            return [false, 'must be a finite number'];
        }

        return \is_int($value) || \is_float($value) || \is_bool($value)
            ? [true, $value]
            : [false, 'must be a text, a number or a boolean'];
    }

    /**
     * @param callable(mixed): array{0: bool, 1: mixed} $item
     *
     * @return array{0: bool, 1: mixed}
     */
    private function acceptList(mixed $value, callable $item): array
    {
        if (!\is_array($value) || !array_is_list($value) || [] === $value) {
            return [false, 'must be a non-empty list'];
        }

        if (\count($value) > $this->maxItems) {
            return [false, \sprintf('holds more than %d items', $this->maxItems)];
        }

        $kept = [];
        $seen = [];

        foreach ($value as $index => $one) {
            [$accepted, $result] = $item($one);

            if (!$accepted) {
                return [false, \sprintf('item %d %s', $index, \is_string($result) ? $result : 'is refused')];
            }

            // The JSON form tells 1 from 1.0 and from "1", which is all a duplicate needs.
            $key = json_encode($result, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);

            if (isset($seen[$key])) {
                return [false, \sprintf('item %d is given twice', $index)];
            }

            $seen[$key] = $index;
            $kept[] = $result;
        }

        return [true, $kept];
    }
}
