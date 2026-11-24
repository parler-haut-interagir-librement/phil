<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;

/**
 * Units of one layer a consignor still had in the shop at an instant, and how many whole days they had been there.
 */
final readonly class HeldUnits
{
    public function __construct(
        public string $layer,
        public string $sku,
        public string $location,
        public int $units,
        public DateTimeImmutable $receivedAt,
        public int $ageInDays,
    ) {
    }

    public static function at(string $layer, string $sku, string $location, int $units, DateTimeImmutable $receivedAt, DateTimeImmutable $at): self
    {
        return new self($layer, $sku, $location, $units, $receivedAt, max(0, intdiv($at->getTimestamp() - $receivedAt->getTimestamp(), 86_400)));
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $string = static fn (string $key): string => \is_string($row[$key] ?? null) ? $row[$key] : throw InvalidValue::because(\sprintf('Units held hold a string "%s".', $key));
        $int = static fn (string $key): int => \is_int($row[$key] ?? null) ? $row[$key] : throw InvalidValue::because(\sprintf('Units held hold an integer "%s".', $key));

        return new self($string('layer'), $string('sku'), $string('location'), $int('units'), new DateTimeImmutable($string('received_at'))->setTimezone(new DateTimeZone('UTC')), $int('age_in_days'));
    }

    /**
     * @return array{layer: string, sku: string, location: string, units: int, received_at: string, age_in_days: int}
     */
    public function toArray(): array
    {
        return [
            'layer' => $this->layer,
            'sku' => $this->sku,
            'location' => $this->location,
            'units' => $this->units,
            'received_at' => $this->receivedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'age_in_days' => $this->ageInDays,
        ];
    }
}
