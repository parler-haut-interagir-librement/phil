<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;

/**
 * The settings a definition gives a trigger or an action: names to scalars or short lists of scalars, bounded so that
 * a definition stays small. A secret is never an option: an option names it, and a port of the application gives it.
 */
final readonly class Options
{
    public const int MAX_ENTRIES = 32;

    public const int MAX_STRING_BYTES = 2048;

    public const int MAX_LIST_ITEMS = 64;

    /**
     * @param array<string, int|bool|string|list<int|string>|null> $values
     */
    private function __construct(
        public array $values,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @throws InvalidDefinition when an entry is not a name to a scalar or a list of scalars
     */
    public static function of(array $values): self
    {
        if (\count($values) > self::MAX_ENTRIES) {
            throw InvalidDefinition::because(\sprintf('Options hold at most %d entries; these hold %d.', self::MAX_ENTRIES, \count($values)));
        }

        $checked = [];

        foreach ($values as $key => $value) {
            if (!\is_string($key) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key)) {
                throw InvalidDefinition::because(\sprintf('"%s" is not an option name: a lowercase letter, then up to 63 lowercase letters, digits or "_".', $key));
            }

            $checked[$key] = self::value($key, $value);
        }

        ksort($checked);

        return new self($checked);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    /**
     * @return int|bool|string|list<int|string>|null
     */
    public function get(string $name): int|bool|string|array|null
    {
        return $this->values[$name] ?? null;
    }

    public function string(string $name): ?string
    {
        $value = $this->values[$name] ?? null;

        return \is_string($value) ? $value : null;
    }

    public function integer(string $name): ?int
    {
        $value = $this->values[$name] ?? null;

        return \is_int($value) ? $value : null;
    }

    public function boolean(string $name): ?bool
    {
        $value = $this->values[$name] ?? null;

        return \is_bool($value) ? $value : null;
    }

    /**
     * @return int|bool|string|list<int|string>|null
     */
    private static function value(string $key, mixed $value): int|bool|string|array|null
    {
        if (\is_array($value)) {
            if (!array_is_list($value) || \count($value) > self::MAX_LIST_ITEMS) {
                throw InvalidDefinition::because(\sprintf('The option "%s" is a list of at most %d items, without keys.', $key, self::MAX_LIST_ITEMS));
            }

            $items = [];

            foreach ($value as $item) {
                if (!\is_int($item) && !\is_string($item)) {
                    throw InvalidDefinition::because(\sprintf('The list of the option "%s" holds integers and strings only.', $key));
                }

                $items[] = \is_string($item) ? self::checkedString($key, $item) : $item;
            }

            return $items;
        }

        if (\is_string($value)) {
            return self::checkedString($key, $value);
        }

        if (\is_int($value) || \is_bool($value) || null === $value) {
            return $value;
        }

        throw InvalidDefinition::because(\sprintf('The option "%s" is a %s: an option is an integer, a boolean, null, a string or a list of them.', $key, get_debug_type($value)));
    }

    private static function checkedString(string $key, string $value): string
    {
        if (\strlen($value) > self::MAX_STRING_BYTES || 1 !== preg_match('//u', $value) || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw InvalidDefinition::because(\sprintf('The option "%s" is valid UTF-8 of at most %d bytes, without control characters.', $key, self::MAX_STRING_BYTES));
        }

        return $value;
    }
}
