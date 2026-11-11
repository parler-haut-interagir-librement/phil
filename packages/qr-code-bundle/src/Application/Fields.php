<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Application;

use Phil\QrCode\Exception\InvalidContent;

/**
 * The values of an array read by their type for `ContentFactory`, a missing value read as empty.
 */
final readonly class Fields
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    /**
     * @throws InvalidContent when the value is neither a string nor missing
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? $default;

        return \is_string($value) ? $value : throw InvalidContent::of($key, 'a text');
    }

    /**
     * A list of texts; a single text is a list of one.
     *
     * @return list<string>
     *
     * @throws InvalidContent when the value is neither a text, a list of texts nor missing
     */
    public function strings(string $key): array
    {
        $value = $this->data[$key] ?? [];
        $value = \is_string($value) ? [$value] : $value;

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidContent::of($key, 'a text or a list of texts');
        }

        return array_map(static fn (mixed $item): string => \is_string($item) ? $item : throw InvalidContent::of($key, 'a text or a list of texts'), $value);
    }

    /**
     * @throws InvalidContent when the value is neither a number nor missing
     */
    public function number(string $key): ?float
    {
        $value = $this->data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        return \is_float($value) || \is_int($value) ? $value : throw InvalidContent::of($key, 'a number');
    }

    /**
     * @throws InvalidContent when the value is neither a boolean nor missing
     */
    public function bool(string $key): bool
    {
        $value = $this->data[$key] ?? false;

        return \is_bool($value) ? $value : throw InvalidContent::of($key, 'true or false');
    }

    /**
     * The fields of a nested array, or null when it is missing.
     *
     * @param list<string> $keys
     *
     * @throws InvalidContent when the value is not an array of those keys
     */
    public function fields(string $key, array $keys): ?self
    {
        $value = $this->data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_array($value) || [] !== array_diff(array_keys($value), $keys)) {
            throw InvalidContent::of($key, 'made of the keys '.implode(', ', $keys));
        }

        return new self($value);
    }
}
