<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

use JsonException;
use Phil\PagedMedia\Gotenberg\Exception\UnexpectedResponse;

/**
 * A decoded JSON value read with its expected types: a value of another type is an `UnexpectedResponse`, never a
 * silent cast.
 *
 * @internal
 */
final readonly class JsonValue
{
    private function __construct(private mixed $value, private string $path)
    {
    }

    public static function decode(string $json): self
    {
        try {
            return new self(json_decode($json, true, flags: \JSON_THROW_ON_ERROR), '$');
        } catch (JsonException $jsonException) {
            throw new UnexpectedResponse(\sprintf('Gotenberg answered with something other than JSON: %s', $jsonException->getMessage()), 200, null, $jsonException);
        }
    }

    /**
     * The members of an object, by key: for a read route, one entry per PDF under its file name.
     *
     * @return array<string, self>
     */
    public function members(): array
    {
        $members = [];

        foreach ($this->array() as $key => $value) {
            $members[(string) $key] = new self($value, $this->path.'.'.$key);
        }

        return $members;
    }

    /**
     * The items of a list.
     *
     * @return list<self>
     */
    public function items(): array
    {
        if (!array_is_list($this->array())) {
            throw $this->unexpected($this->path, 'a list');
        }

        return array_map(fn (int $index): self => new self($this->array()[$index], $this->path.'['.$index.']'), array_keys($this->array()));
    }

    public function string(string $key): string
    {
        $value = $this->member($key);

        return \is_string($value) ? $value : throw $this->unexpected($this->path.'.'.$key, 'a string');
    }

    public function optionalString(string $key): ?string
    {
        return null === $this->member($key) ? null : $this->string($key);
    }

    public function int(string $key): int
    {
        $value = $this->member($key);

        return \is_int($value) ? $value : throw $this->unexpected($this->path.'.'.$key, 'an integer');
    }

    public function optionalInt(string $key): ?int
    {
        return null === $this->member($key) ? null : $this->int($key);
    }

    public function bool(string $key): bool
    {
        $value = $this->member($key);

        return \is_bool($value) ? $value : throw $this->unexpected($this->path.'.'.$key, 'a boolean');
    }

    /**
     * A boolean that the route leaves out when it is false.
     */
    public function flag(string $key): bool
    {
        return null !== $this->member($key) && $this->bool($key);
    }

    /**
     * The items of a list the route leaves out when it is empty.
     *
     * @return list<self>
     */
    public function optionalList(string $key): array
    {
        return null === $this->member($key) ? [] : new self($this->member($key), $this->path.'.'.$key)->items();
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        return array_map(static fn (self $item): string => \is_string($item->value) ? $item->value : throw $item->unexpected($item->path, 'a string'), $this->optionalList($key));
    }

    /**
     * @return list<int>
     */
    public function ints(string $key): array
    {
        return array_map(static fn (self $item): int => \is_int($item->value) ? $item->value : throw $item->unexpected($item->path, 'an integer'), $this->optionalList($key));
    }

    /**
     * A string, or a list of strings.
     *
     * @return string|list<string>
     */
    public function stringOrStrings(string $key): string|array
    {
        return \is_array($this->member($key)) ? $this->strings($key) : $this->string($key);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function array(): array
    {
        return \is_array($this->value) ? $this->value : throw $this->unexpected($this->path, 'an object or a list');
    }

    private function member(string $key): mixed
    {
        return $this->array()[$key] ?? null;
    }

    private function unexpected(string $path, string $expected): UnexpectedResponse
    {
        return UnexpectedResponse::because(\sprintf('Gotenberg answered JSON whose %s is not %s.', $path, $expected));
    }
}
