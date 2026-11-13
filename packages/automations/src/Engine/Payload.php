<?php

declare(strict_types=1);

namespace Phil\Automations\Engine;

use Phil\Automations\Exception\InvalidPayload;

/**
 * What a trigger hands to a run, or what an action gives back: identifiers only. Each key is a name, each value an
 * integer, a boolean, `null` or a short string without control characters — an order number, a list key, a status —,
 * never a name, an address or a message body. What is heavy or personal is read by the action from its own store,
 * by the identifier (a claim check), so that a run, its messages and its table never hold it.
 */
final readonly class Payload
{
    public const int MAX_ENTRIES = 32;

    public const int MAX_STRING_BYTES = 255;

    /**
     * @param array<string, int|bool|string|null> $values
     */
    private function __construct(
        public array $values,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @throws InvalidPayload when an entry is not an identifier
     */
    public static function of(array $values): self
    {
        if (\count($values) > self::MAX_ENTRIES) {
            throw InvalidPayload::because(\sprintf('A payload holds at most %d entries; this one holds %d.', self::MAX_ENTRIES, \count($values)));
        }

        $checked = [];

        foreach ($values as $key => $value) {
            if (!\is_string($key) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key)) {
                throw InvalidPayload::because(\sprintf('"%s" is not a payload key: a lowercase letter, then up to 63 lowercase letters, digits or "_".', $key));
            }

            if (\is_string($value) && (\strlen($value) > self::MAX_STRING_BYTES || 1 === preg_match('/[\x00-\x1F\x7F]/', $value) || 1 !== preg_match('//u', $value))) {
                throw InvalidPayload::because(\sprintf('The payload value "%s" is not an identifier: valid UTF-8 of at most %d bytes, without control characters.', $key, self::MAX_STRING_BYTES));
            }

            if (!\is_string($value) && !\is_int($value) && !\is_bool($value) && null !== $value) {
                throw InvalidPayload::because(\sprintf('The payload value "%s" is a %s: only integers, booleans, null and short strings are identifiers.', $key, get_debug_type($value)));
            }

            $checked[$key] = $value;
        }

        ksort($checked);

        return new self($checked);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $key): int|bool|string|null
    {
        return $this->values[$key] ?? null;
    }

    /**
     * A string or integer value as a string, for an action that reads an identifier; `null` when the key is missing,
     * empty or holds another type.
     */
    public function identifier(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return match (true) {
            \is_int($value) => (string) $value,
            \is_string($value) && '' !== $value => $value,
            default => null,
        };
    }
}
