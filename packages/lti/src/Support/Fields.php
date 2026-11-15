<?php

declare(strict_types=1);

namespace Phil\Lti\Support;

use DateTimeImmutable;
use Exception;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\InvalidToken;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use RuntimeException;

/**
 * Typed reads of the members of a JSON object — the claims of a token, a document of a service —, each bounded: a
 * string of 4,096 bytes at most by default, a list of 256 entries at most. A member of the wrong type fails with
 * `InvalidToken` in a token and `InvalidData` in a document, naming the member.
 *
 * @internal
 */
final readonly class Fields
{
    public const int MAX_STRING = 4096;

    public const int MAX_LIST = 256;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        public array $data,
        private bool $inToken = true,
        private bool $allowLocalhost = false,
        private string $path = '',
    ) {
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function string(string $name, int $max = self::MAX_STRING): string
    {
        return $this->optionalString($name, $max) ?? throw $this->fail(\sprintf('The member "%s" is required.', $this->path.$name));
    }

    public function optionalString(string $name, int $max = self::MAX_STRING): ?string
    {
        $value = $this->data[$name] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || \strlen($value) > $max || !mb_check_encoding($value, 'UTF-8')) {
            throw $this->fail(\sprintf('The member "%s" is a UTF-8 string of %d bytes at most.', $this->path.$name, $max));
        }

        return $value;
    }

    public function optionalInt(string $name): ?int
    {
        $value = $this->data[$name] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw $this->fail(\sprintf('The member "%s" is an integer.', $this->path.$name));
        }

        return $value;
    }

    public function optionalNumber(string $name): ?float
    {
        $value = $this->data[$name] ?? null;

        if (null === $value) {
            return null;
        }

        if ((!\is_int($value) && !\is_float($value)) || !is_finite((float) $value)) {
            throw $this->fail(\sprintf('The member "%s" is a number.', $this->path.$name));
        }

        return (float) $value;
    }

    public function bool(string $name, bool $default = false): bool
    {
        $value = $this->data[$name] ?? $default;

        if (!\is_bool($value)) {
            throw $this->fail(\sprintf('The member "%s" is a boolean.', $this->path.$name));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function stringList(string $name, int $max = self::MAX_LIST): array
    {
        $value = $this->data[$name] ?? [];

        if (!\is_array($value) || !array_is_list($value) || \count($value) > $max) {
            throw $this->fail(\sprintf('The member "%s" is a list of %d strings at most.', $this->path.$name, $max));
        }

        $strings = [];

        foreach ($value as $entry) {
            if (!\is_string($entry) || \strlen($entry) > self::MAX_STRING) {
                throw $this->fail(\sprintf('The member "%s" is a list of strings.', $this->path.$name));
            }

            $strings[] = $entry;
        }

        return $strings;
    }

    public function url(string $name): Url
    {
        return $this->optionalUrl($name) ?? throw $this->fail(\sprintf('The member "%s" is required.', $this->path.$name));
    }

    public function optionalUrl(string $name): ?Url
    {
        $text = $this->optionalString($name, Url::MAX_LENGTH);

        if (null === $text) {
            return null;
        }

        try {
            return Url::fromString($text, $this->allowLocalhost);
        } catch (UrlException $urlException) {
            throw $this->fail(\sprintf('The member "%s" is not a web address: %s', $this->path.$name, $urlException->getMessage()));
        }
    }

    public function optionalDate(string $name): ?DateTimeImmutable
    {
        $text = $this->optionalString($name, 64);

        if (null === $text) {
            return null;
        }

        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(Z|[+-]\d{2}:\d{2})$/D', $text)) {
            throw $this->fail(\sprintf('The member "%s" is a date and time of ISO 8601 with its offset.', $this->path.$name));
        }

        try {
            return new DateTimeImmutable($text);
        } catch (Exception $exception) {
            throw $this->fail(\sprintf('The member "%s" is not a valid date: %s', $this->path.$name, $exception->getMessage()));
        }
    }

    public function object(string $name): self
    {
        return $this->optionalObject($name) ?? throw $this->fail(\sprintf('The member "%s" is required.', $this->path.$name));
    }

    public function optionalObject(string $name): ?self
    {
        $value = $this->data[$name] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw $this->fail(\sprintf('The member "%s" is an object.', $this->path.$name));
        }

        return new self($value, $this->inToken, $this->allowLocalhost, $this->path.$name.'.');
    }

    /**
     * The entries of a list of objects.
     *
     * @return list<self>
     */
    public function objectList(string $name, int $max = self::MAX_LIST): array
    {
        $value = $this->data[$name] ?? [];

        if (!\is_array($value) || !array_is_list($value) || \count($value) > $max) {
            throw $this->fail(\sprintf('The member "%s" is a list of %d objects at most.', $this->path.$name, $max));
        }

        $objects = [];

        foreach ($value as $index => $entry) {
            if (!\is_array($entry) || ([] !== $entry && array_is_list($entry))) {
                throw $this->fail(\sprintf('The member "%s" is a list of objects.', $this->path.$name));
            }

            $objects[] = new self($entry, $this->inToken, $this->allowLocalhost, $this->path.$name.'['.$index.'].');
        }

        return $objects;
    }

    /**
     * A map of names to strings, as the custom claim: numbers and booleans are written as text, as platforms often send
     * them.
     *
     * @return array<string, string>
     */
    public function stringMap(string $name, int $max = self::MAX_LIST): array
    {
        $value = $this->data[$name] ?? [];

        if (!\is_array($value) || ([] !== $value && array_is_list($value)) || \count($value) > $max) {
            throw $this->fail(\sprintf('The member "%s" is an object of %d members at most.', $this->path.$name, $max));
        }

        $map = [];

        foreach ($value as $key => $entry) {
            $text = match (true) {
                \is_string($entry) => $entry,
                \is_int($entry), \is_float($entry) => (string) $entry,
                \is_bool($entry) => $entry ? 'true' : 'false',
                default => throw $this->fail(\sprintf('The member "%s" maps names to strings.', $this->path.$name)),
            };

            if (\strlen($text) > self::MAX_STRING || \strlen((string) $key) > 255) {
                throw $this->fail(\sprintf('The member "%s" maps names of 255 bytes to strings of %d bytes at most.', $this->path.$name, self::MAX_STRING));
            }

            $map[(string) $key] = $text;
        }

        return $map;
    }

    public function fail(string $reason): RuntimeException
    {
        return $this->inToken ? InvalidToken::because($reason) : InvalidData::because($reason);
    }
}
