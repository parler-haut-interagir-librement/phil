<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Internal;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;

/**
 * Reads the values of an array decoded from storage, each of the type expected, and names the key of a value that is
 * not, from the root of the array.
 *
 * @internal
 */
final readonly class Reader
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        private array $data,
        private string $path,
    ) {
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : throw $this->invalid($key, 'a string');
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return null === $value || \is_string($value) ? $value : throw $this->invalid($key, 'a string or null');
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) ? $value : throw $this->invalid($key, 'an integer');
    }

    public function float(string $key): float
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) || \is_float($value) ? $value : throw $this->invalid($key, 'a number');
    }

    public function reader(string $key): self
    {
        $value = $this->data[$key] ?? null;

        return \is_array($value) ? new self($value, $this->path.'.'.$key) : throw $this->invalid($key, 'an object');
    }

    public function nullableReader(string $key): ?self
    {
        return null === ($this->data[$key] ?? null) ? null : $this->reader($key);
    }

    /**
     * @return list<self>
     */
    public function readers(string $key): array
    {
        $readers = [];

        foreach ($this->list($key) as $index => $item) {
            $readers[] = \is_array($item) ? new self($item, \sprintf('%s.%s[%d]', $this->path, $key, $index)) : throw $this->invalid(\sprintf('%s[%d]', $key, $index), 'an object');
        }

        return $readers;
    }

    /**
     * @return list<mixed>
     */
    public function list(string $key): array
    {
        $value = $this->data[$key] ?? null;

        return \is_array($value) && array_is_list($value) ? $value : throw $this->invalid($key, 'a list');
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $strings = [];

        foreach ($this->list($key) as $index => $item) {
            $strings[] = \is_string($item) ? $item : throw $this->invalid(\sprintf('%s[%d]', $key, $index), 'a string');
        }

        return $strings;
    }

    /**
     * @return array<array-key, string> by key; a key of digits is an integer, as PHP keys it
     */
    public function stringMap(string $key): array
    {
        $map = [];

        foreach ($this->map($key) as $name => $item) {
            $map[$name] = \is_string($item) ? $item : throw $this->invalid($key.'.'.$name, 'a string');
        }

        return $map;
    }

    /**
     * @return array<array-key, mixed> by key; a key of digits is an integer, as PHP keys it
     */
    public function map(string $key): array
    {
        $value = $this->data[$key] ?? null;

        return \is_array($value) ? $value : throw $this->invalid($key, 'an object');
    }

    /**
     * Reads the version the array was written in, refusing any other.
     */
    public function version(int $expected): void
    {
        if ($this->int('version') !== $expected) {
            throw InvalidDefinition::because(\sprintf('%s is written in version %d; this library reads version %d.', $this->path, $this->int('version'), $expected));
        }
    }

    private function invalid(string $key, string $expected): InvalidDefinition
    {
        return InvalidDefinition::because(\sprintf('%s.%s is not %s.', $this->path, $key, $expected));
    }
}
