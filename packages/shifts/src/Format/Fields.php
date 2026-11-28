<?php

declare(strict_types=1);

namespace Phil\Shifts\Format;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Shifts\Exception\InvalidDocument;

/**
 * The reader of the fields of a decoded document: each accessor returns the field of the expected type or throws an
 * InvalidDocument that names its path, never its value.
 *
 * @internal
 */
final readonly class Fields
{
    /**
     * @param array<array-key, mixed> $data
     */
    private function __construct(
        private array $data,
        private string $path,
    ) {
    }

    public static function of(mixed $data, string $path): self
    {
        if (!\is_array($data)) {
            throw InvalidDocument::at($path, 'must be an object');
        }

        return new self($data, $path);
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidDocument::at($this->path.'.'.$key, 'must be a string');
        }

        return $value;
    }

    public function optionalString(string $key): string
    {
        return $this->has($key) ? $this->string($key) : '';
    }

    public function nullableString(string $key): ?string
    {
        return $this->has($key) ? $this->string($key) : null;
    }

    public function int(string $key, ?int $default = null): int
    {
        $value = $this->data[$key] ?? $default;

        if (!\is_int($value)) {
            throw InvalidDocument::at($this->path.'.'.$key, 'must be a whole number');
        }

        return $value;
    }

    public function nullableInt(string $key): ?int
    {
        return $this->has($key) ? $this->int($key) : null;
    }

    public function bool(string $key): bool
    {
        $value = $this->data[$key] ?? false;

        if (!\is_bool($value)) {
            throw InvalidDocument::at($this->path.'.'.$key, 'must be true or false');
        }

        return $value;
    }

    /**
     * An instant written as RFC 3339 with its offset — `2026-11-28T09:00:00Z` or `2026-11-28T09:00:00-05:00` —, read
     * in UTC. The date must exist, the time within 23:59:59 and the offset within 23:59.
     */
    public function instant(string $key): DateTimeImmutable
    {
        $value = $this->string($key);
        $number = static fn (?string $digits): int => (int) $digits;

        if (1 !== preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})T(?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})(?:Z|[+-](?<offsetHour>\d{2}):(?<offsetMinute>\d{2}))$/D', $value, $parts, \PREG_UNMATCHED_AS_NULL)
            || !checkdate($number($parts['month']), $number($parts['day']), $number($parts['year']))
            || $number($parts['hour']) > 23 || $number($parts['minute']) > 59 || $number($parts['second']) > 59
            || $number($parts['offsetHour']) > 23 || $number($parts['offsetMinute']) > 59) {
            throw InvalidDocument::at($this->path.'.'.$key, 'must be an instant such as 2026-11-28T09:00:00Z');
        }

        return new DateTimeImmutable($value)->setTimezone(new DateTimeZone('UTC'));
    }

    public function nullableInstant(string $key): ?DateTimeImmutable
    {
        return $this->has($key) ? $this->instant($key) : null;
    }

    /**
     * @return list<mixed>
     */
    public function list(string $key, int $max): array
    {
        $value = $this->data[$key] ?? [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidDocument::at($this->path.'.'.$key, 'must be a list');
        }

        if (\count($value) > $max) {
            throw InvalidDocument::at($this->path.'.'.$key, \sprintf('holds %d entries at most', $max));
        }

        return $value;
    }

    public function path(string $key): string
    {
        return $this->path.'.'.$key;
    }

    public static function write(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
