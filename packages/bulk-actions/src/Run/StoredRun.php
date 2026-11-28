<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

use DateTimeImmutable;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;

/**
 * @internal reads the array a run store gives back, refusing what toArray() cannot have written
 */
final readonly class StoredRun
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    public function string(string $key): string
    {
        return $this->optionalString($key) ?? throw $this->missing($key);
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw $this->missing($key);
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->data[$key] ?? null;

        return \is_bool($value) ? $value : throw $this->missing($key);
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) ? $value : throw $this->missing($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function map(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if (!\is_array($value)) {
            throw $this->missing($key);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    public function date(string $key): ?DateTimeImmutable
    {
        $value = $this->optionalString($key);

        if (null === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(\DATE_ATOM, $value);

        return false === $date ? throw $this->missing($key) : $date;
    }

    /**
     * @return array<array-key, array{0: ItemOutcome, 1: string|null}>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->map('items') as $row) {
            if (!\is_array($row) || !\is_string($row[0] ?? null) || !\is_string($row[1] ?? null) || !\is_string($row[2] ?? null) && null !== ($row[2] ?? null)) {
                throw $this->missing('items');
            }

            $outcome = ItemOutcome::tryFrom($row[1]) ?? throw $this->missing('items');
            $items[Check::identifier('identifier', $row[0])] = [$outcome, $row[2] ?? null];
        }

        return $items;
    }

    private function missing(string $key): InvalidInput
    {
        return InvalidInput::because('stored run', 'must have a valid '.$key);
    }
}
