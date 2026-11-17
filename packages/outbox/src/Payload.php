<?php

declare(strict_types=1);

namespace Phil\Outbox;

use JsonException;
use Phil\Outbox\Exception\InvalidRecord;

/**
 * The few identifiers a record carries, by name: strings, integers, booleans or null, never a nested value nor a float.
 * The event is rebuilt from them, its heavy data read again from where the package keeps it; a payload carries no
 * personal data, which would outlive its erasure in the delivered records until their retention ends.
 *
 * A name is 1 to 64 letters, digits or `_`, starting with a lowercase letter; a string is valid UTF-8 of at most 255
 * bytes; a payload holds at most 16 values and its JSON at most 4,096 bytes. The same bounds apply to a payload read
 * back from a store.
 */
final readonly class Payload
{
    /** The most values a payload holds. */
    public const int MAX_VALUES = 16;

    /** The longest string value, in bytes. */
    public const int MAX_STRING_BYTES = 255;

    /** The longest JSON of a payload, in bytes. */
    public const int MAX_JSON_BYTES = 4096;

    /** The shape of a name. */
    public const string NAME_PATTERN = '/^[a-z][A-Za-z0-9_]{0,63}$/D';

    /**
     * @param array<non-empty-string, string|int|bool|null> $values
     */
    private function __construct(
        public array $values,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values the identifiers, by name
     *
     * @throws InvalidRecord when a name, a value or the size is out of bounds
     */
    public static function of(array $values): self
    {
        if (\count($values) > self::MAX_VALUES) {
            throw InvalidRecord::because(\sprintf('A payload holds at most %d values, not %d.', self::MAX_VALUES, \count($values)));
        }

        $checked = [];

        foreach ($values as $name => $value) {
            $name = self::name($name);
            $checked[$name] = self::value($name, $value);
        }

        $payload = new self($checked);

        if (\strlen($payload->toJson()) > self::MAX_JSON_BYTES) {
            throw InvalidRecord::because(\sprintf('A payload takes at most %d bytes of JSON.', self::MAX_JSON_BYTES));
        }

        return $payload;
    }

    /**
     * Reads back the JSON a store kept, under the same bounds as a new payload.
     *
     * @throws InvalidRecord when the text is not the JSON object of a payload
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidRecord::because(\sprintf('A payload takes at most %d bytes of JSON.', self::MAX_JSON_BYTES));
        }

        try {
            $values = json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidRecord::because('A payload is a JSON object of identifiers, one level deep.', $jsonException);
        }

        if (!\is_array($values)) {
            throw InvalidRecord::because('A payload is a JSON object of identifiers, one level deep.');
        }

        return self::of($values);
    }

    /**
     * The JSON object of the values, as a store keeps it.
     */
    public function toJson(): string
    {
        return json_encode($this->values, \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    /**
     * The value of a name, null when it is null or missing.
     */
    public function get(string $name): string|int|bool|null
    {
        return $this->values[$name] ?? null;
    }

    /**
     * @throws InvalidRecord when the name is missing or its value is not a string
     */
    public function string(string $name): string
    {
        $value = $this->values[$name] ?? null;

        if (!\is_string($value)) {
            throw InvalidRecord::because(\sprintf('The payload has no string "%s".', $name));
        }

        return $value;
    }

    /**
     * @throws InvalidRecord when the name is missing or its value is not an integer
     */
    public function int(string $name): int
    {
        $value = $this->values[$name] ?? null;

        if (!\is_int($value)) {
            throw InvalidRecord::because(\sprintf('The payload has no integer "%s".', $name));
        }

        return $value;
    }

    /**
     * @return non-empty-string
     */
    private static function name(int|string $name): string
    {
        if (!\is_string($name) || 1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw InvalidRecord::because(\sprintf('"%s" is not a payload name: 1 to 64 letters, digits or "_", starting with a lowercase letter.', mb_strimwidth((string) $name, 0, 70, '…')));
        }

        return $name;
    }

    private static function value(string $name, mixed $value): string|int|bool|null
    {
        if (\is_string($value)) {
            if (\strlen($value) > self::MAX_STRING_BYTES || !mb_check_encoding($value, 'UTF-8')) {
                throw InvalidRecord::because(\sprintf('The value of "%s" is not valid UTF-8 of at most %d bytes.', $name, self::MAX_STRING_BYTES));
            }

            return $value;
        }

        if (null === $value || \is_int($value) || \is_bool($value)) {
            return $value;
        }

        throw InvalidRecord::because(\sprintf('The value of "%s" is a %s: a payload holds strings, integers, booleans and null.', $name, get_debug_type($value)));
    }
}
