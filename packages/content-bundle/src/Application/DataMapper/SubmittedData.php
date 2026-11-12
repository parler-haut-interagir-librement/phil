<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\DataMapper;

use Phil\ContentBundle\Domain\Exception\InvalidContent;

/**
 * Reads typed values out of submitted data, for the data mappers.
 *
 * **An absent key changes nothing; a key set to `null` clears the value.** That is what lets a form submit
 * only the fields it shows. Strings are trimmed, and a string empty once trimmed counts as `null`: an
 * emptied field clears its value. A value of the wrong type is refused with the key it came under.
 */
final class SubmittedData
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function has(array $data, string $key): bool
    {
        return \array_key_exists($key, $data);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidContent
     */
    public static function string(array $data, string $key): ?string
    {
        $value = self::untrimmedString($data, $key);

        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * A string kept as written, for a source whose leading spaces mean something; blank counts as `null`.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidContent
     */
    public static function untrimmedString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidContent::because(\sprintf('"%s" must be a string or null; got %s.', $key, get_debug_type($value)));
        }

        return '' === trim($value) ? null : $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidContent
     */
    public static function bool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        if (null !== $value && !\is_bool($value)) {
            throw InvalidContent::because(\sprintf('"%s" must be a boolean or null; got %s.', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * A list of strings, each trimmed, the empty ones dropped; or one string of comma-separated items, as a
     * single text field submits it. `null` is the empty list.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     *
     * @throws InvalidContent
     */
    public static function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return [];
        }

        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (!\is_array($value)) {
            throw InvalidContent::because(\sprintf('"%s" must be a list of strings, a comma-separated string or null; got %s.', $key, get_debug_type($value)));
        }

        $items = [];

        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw InvalidContent::because(\sprintf('"%s" must hold strings only; got %s.', $key, get_debug_type($item)));
            }

            $item = trim($item);

            if ('' !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>|null
     *
     * @throws InvalidContent
     */
    public static function map(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        if (null !== $value && !\is_array($value)) {
            throw InvalidContent::because(\sprintf('"%s" must be an array or null; got %s.', $key, get_debug_type($value)));
        }

        return $value;
    }
}
