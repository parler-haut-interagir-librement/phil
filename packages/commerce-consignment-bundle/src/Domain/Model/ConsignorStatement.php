<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\HeldUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * What a consignor sold and is owed over a period, in one currency, as the ledger's entries told it when the statement
 * was issued: numbered, then never changed. A statement issued again for the same period is a new one, with a number of
 * its own; the first stays.
 *
 * Its totals are the sums of its lines, and its share and commission add up to its revenue: no cent lost or created.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_consignment_statement')]
#[ORM\UniqueConstraint(name: 'phil_commerce_consignment_statement_number', columns: ['number'])]
#[ORM\Index(name: 'phil_commerce_consignment_statement_consignor', columns: ['consignor', 'period_start'])]
final class ConsignorStatement
{
    /** The most lines one statement holds: a longer period is split in shorter ones. */
    public const int MAX_LINES = 5000;

    /** The most layers whose held units one statement lists. */
    public const int MAX_HELD = 1000;

    /**
     * @param list<array<string, int|string|null>> $lines
     * @param list<array<string, int|string>>      $held
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'number', length: 32)]
        public readonly string $number,
        #[ORM\Column(name: 'consignor', length: 64)]
        public readonly string $consignor,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currency,
        #[ORM\Column(name: 'period_start', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $periodStart,
        #[ORM\Column(name: 'period_end', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $periodEnd,
        #[ORM\Column(name: 'issued_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $issuedAt,
        #[ORM\Column(name: 'units_sold', type: 'integer')]
        public readonly int $unitsSold,
        #[ORM\Column(name: 'units_returned', type: 'integer')]
        public readonly int $unitsReturned,
        #[ORM\Column(name: 'units_bought_out', type: 'integer')]
        public readonly int $unitsBoughtOut,
        #[ORM\Column(name: 'units_given_back', type: 'integer')]
        public readonly int $unitsGivenBack,
        #[ORM\Column(name: 'revenue', type: 'bigint')]
        private readonly int $revenue,
        #[ORM\Column(name: 'share', type: 'bigint')]
        private readonly int $share,
        #[ORM\Column(name: 'commission', type: 'bigint')]
        private readonly int $commission,
        #[ORM\Column(name: 'entry_lines', type: 'json')]
        private readonly array $lines,
        #[ORM\Column(name: 'held_units', type: 'json')]
        private readonly array $held,
    ) {
    }

    /**
     * @param list<StatementLine> $lines
     * @param list<HeldUnits>     $held
     */
    public static function issue(string $number, Owner $consignor, Currency $currency, StatementPeriod $period, array $lines, array $held, DateTimeImmutable $issuedAt): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,32}$/D', $number)) {
            throw InvalidValue::because(\sprintf('A statement number has 1 to 32 visible ASCII characters; "%s" does not.', mb_substr($number, 0, 40)));
        }

        if (!$consignor->isConsigned()) {
            throw InvalidValue::because('A statement is issued to a consignor, not to the shop.');
        }

        if (\count($lines) > self::MAX_LINES || \count($held) > self::MAX_HELD) {
            throw InvalidValue::because(\sprintf('A statement holds %d lines and %d layers held at most: split the period.', self::MAX_LINES, self::MAX_HELD));
        }

        $sold = 0;
        $returned = 0;
        $boughtOut = 0;
        $givenBack = 0;
        $revenue = Money::zero($currency);
        $share = $revenue;
        $commission = $revenue;

        foreach ($lines as $line) {
            match ($line->kind) {
                EntryKind::Sold => $sold += $line->units,
                EntryKind::Returned => $returned -= $line->units,
                EntryKind::BoughtOut => $boughtOut += $line->units,
                EntryKind::GivenBack => $givenBack += $line->units,
                default => throw InvalidValue::because(\sprintf('A statement lists units sold, returned, bought out or given back; not %s.', $line->kind->value)),
            };
            $revenue = $revenue->add($line->revenue);
            $share = $share->add($line->share);
            $commission = $commission->add($line->commission);
        }

        if (!$revenue->equals($share->add($commission))) {
            throw InvalidValue::because(\sprintf('A statement whose share %s and commission %s do not add up to its revenue %s would lose or create money.', $share->toDecimal(), $commission->toDecimal(), $revenue->toDecimal()));
        }

        return new self(
            Uuid::v7()->toRfc4122(),
            $number,
            (string) $consignor->consignor,
            $currency->code,
            $period->start,
            $period->end,
            $issuedAt->setTimezone(new DateTimeZone('UTC')),
            $sold,
            $returned,
            $boughtOut,
            $givenBack,
            $revenue->minorUnits,
            $share->minorUnits,
            $commission->minorUnits,
            array_map(static fn (StatementLine $line): array => $line->toArray(), $lines),
            array_map(static fn (HeldUnits $units): array => $units->toArray(), $held),
        );
    }

    public function currency(): Currency
    {
        return Currency::of($this->currency);
    }

    public function period(): StatementPeriod
    {
        return StatementPeriod::of($this->periodStart, $this->periodEnd);
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    /**
     * What the units sold, bought out and brought back brought in over the period; customers' returns lower it.
     */
    public function revenue(): Money
    {
        return Money::of($this->revenue, $this->currency());
    }

    /**
     * What the shop owes the consignor for the period: their share of the units sold and bought out, less the share of
     * the units customers brought back — sold in this period or in an earlier one.
     */
    public function share(): Money
    {
        return Money::of($this->share, $this->currency());
    }

    /**
     * What the shop kept: the revenue less the consignor's share.
     */
    public function commission(): Money
    {
        return Money::of($this->commission, $this->currency());
    }

    /**
     * @return list<StatementLine> the entries of the period, oldest first
     */
    public function lines(): array
    {
        $currency = $this->currency();

        return array_map(static fn (array $row): StatementLine => StatementLine::fromArray($row, $currency), $this->lines);
    }

    /**
     * @return list<HeldUnits> the consignor's units still in the shop at the end of the period, oldest first
     */
    public function held(): array
    {
        return array_map(HeldUnits::fromArray(...), $this->held);
    }

    public function unitsHeld(): int
    {
        return array_sum(array_map(static fn (HeldUnits $units): int => $units->units, $this->held()));
    }
}
