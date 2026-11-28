<?php

declare(strict_types=1);

namespace Phil\BulkActions\Change;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;

/**
 * The fields a bulk edit sets, and only those: a field left unticked is unchanged, while a ticked field set to null,
 * false, "" or 0 sets that value. An empty change set is the change of an action without fields, such as an archive.
 */
final readonly class ChangeSet
{
    /** The most fields one bulk edit sets. */
    public const int MAX_FIELDS = 64;

    /**
     * @param array<string, scalar|list<scalar|null>|null> $values the new value of each ticked field
     */
    private function __construct(public array $values)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<array-key, mixed> $values the new value of each field to set, null included
     */
    public static function of(array $values): self
    {
        if (\count($values) > self::MAX_FIELDS) {
            throw InvalidInput::because('change', \sprintf('must set %d fields at most', self::MAX_FIELDS));
        }

        $checked = [];

        foreach ($values as $field => $value) {
            $field = Check::name('field', (string) $field);
            $checked[$field] = Check::value('value of "'.$field.'"', $value);
        }

        return new self($checked);
    }

    /**
     * Reads a bulk edit form: one box per field says whether the field changes, so an emptied field is told apart from
     * a field left alone. A ticked field missing from the values is set to null.
     *
     * @param array<array-key, mixed> $values the submitted values, by field
     * @param array<array-key, mixed> $ticked the submitted boxes, by field: "1", "on", "true" or true tick a field
     */
    public static function fromSubmission(array $values, array $ticked): self
    {
        $set = [];

        foreach ($ticked as $field => $box) {
            if (true === $box || (\is_string($box) && \in_array(strtolower($box), ['1', 'on', 'true'], true))) {
                $set[(string) $field] = $values[$field] ?? null;
            }
        }

        return self::of($set);
    }

    public function isEmpty(): bool
    {
        return [] === $this->values;
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->values);
    }

    /**
     * The new value of a ticked field; null for a field left unchanged, which has() tells apart from a field set to null.
     *
     * @return scalar|list<scalar|null>|null
     */
    public function value(string $field): bool|float|int|string|array|null
    {
        return $this->values[$field] ?? null;
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_map(strval(...), array_keys($this->values));
    }
}
