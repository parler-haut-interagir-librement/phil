<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * One entry of the ledger as a statement shows it: units sold, brought back by a customer, bought by the shop or given
 * back, with what they brought in, what the consignor is owed and what the shop kept.
 *
 * Units leaving the consignor count positive, units coming back negative; a customer's return brings in, and owes,
 * negative amounts. The share and the commission always add up to the revenue.
 */
final readonly class StatementLine
{
    private function __construct(
        public DateTimeImmutable $movedAt,
        public ?string $reference,
        public string $sku,
        public string $location,
        public EntryKind $kind,
        public int $units,
        public Money $revenue,
        public Money $share,
        public Money $commission,
    ) {
    }

    public static function fromEntry(LedgerEntry $entry, Currency $currency): self
    {
        $margin = $entry->margin();
        $revenue = $margin->revenue ?? Money::zero($currency);
        $share = $margin->cost ?? Money::zero($currency);

        return new self($entry->movedAt(), $entry->reference, $entry->sku, $entry->location, $entry->kind, -$entry->quantity, $revenue, $share, $revenue->subtract($share));
    }

    /**
     * Reads a line a statement froze.
     *
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row, Currency $currency): self
    {
        $revenue = Money::of(self::int($row, 'revenue'), $currency);
        $share = Money::of(self::int($row, 'share'), $currency);
        $commission = Money::of(self::int($row, 'commission'), $currency);

        if (!$revenue->equals($share->add($commission))) {
            throw InvalidValue::because('A statement line whose share and commission do not add up to its revenue was not frozen by the ledger.');
        }

        return new self(
            new DateTimeImmutable(self::string($row, 'moved_at'))->setTimezone(new DateTimeZone('UTC')),
            null === ($row['reference'] ?? null) ? null : self::string($row, 'reference'),
            self::string($row, 'sku'),
            self::string($row, 'location'),
            EntryKind::from(self::string($row, 'kind')),
            self::int($row, 'units'),
            $revenue,
            $share,
            $commission,
        );
    }

    /**
     * @return array{moved_at: string, reference: string|null, sku: string, location: string, kind: string, units: int, revenue: int, share: int, commission: int}
     */
    public function toArray(): array
    {
        return [
            'moved_at' => $this->movedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'reference' => $this->reference,
            'sku' => $this->sku,
            'location' => $this->location,
            'kind' => $this->kind->value,
            'units' => $this->units,
            'revenue' => $this->revenue->minorUnits,
            'share' => $this->share->minorUnits,
            'commission' => $this->commission->minorUnits,
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return \is_int($value) ? $value : throw InvalidValue::because(\sprintf('A statement line holds an integer "%s".', $key));
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) ? $value : throw InvalidValue::because(\sprintf('A statement line holds a string "%s".', $key));
    }
}
