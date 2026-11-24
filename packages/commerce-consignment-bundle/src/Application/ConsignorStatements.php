<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Application\Contract\StatementNumbering;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\HeldUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\Money\Currency;

/**
 * Issues the statements of the consignors, read from the ledger's entries and never worked out again from the sales:
 * what each sold, got back from customers, had bought by the shop or was given back over a period, what they are owed,
 * and what they still have in the shop, with its age.
 *
 * Nothing is committed here: the caller flushes the statement, and its number with it.
 */
final readonly class ConsignorStatements
{
    public function __construct(
        private LedgerRepository $ledger,
        private ConsignorRepository $consignors,
        private StatementNumbering $numbering,
        private ConsignorAgreements $agreements,
    ) {
    }

    /**
     * Issues a consignor's statement of a period that is over, in one currency: the entries in another currency are left
     * to the statement of that currency.
     */
    public function issue(Owner $consignor, Currency $currency, StatementPeriod $period, DateTimeImmutable $at): ConsignorStatement
    {
        if (!$consignor->isConsigned()) {
            throw ConsignmentRefused::notConsigned('A statement');
        }

        if ($period->end > $at) {
            throw ConsignmentRefused::periodNotOver($period->end->format(\DATE_ATOM), $at->format(\DATE_ATOM));
        }

        $key = $consignor->key();
        $entries = $this->ledger->consignorEntries($key, $period->start, $period->end, ConsignorStatement::MAX_LINES + 1);

        if (\count($entries) > ConsignorStatement::MAX_LINES) {
            throw ConsignmentRefused::tooManyLines($key, ConsignorStatement::MAX_LINES);
        }

        $lines = [];

        foreach ($entries as $entry) {
            if ($this->listed($entry, $currency)) {
                $lines[] = StatementLine::fromEntry($entry, $currency);
            }
        }

        $statement = ConsignorStatement::issue($this->numbering->next(), $consignor, $currency, $period, $lines, $this->held($key, $period->end), $at);
        $this->consignors->addStatement($statement);

        return $statement;
    }

    /**
     * The last period over at the instant, at the frequency of the consignor's agreement then; monthly without one.
     */
    public function lastPeriod(Owner $consignor, DateTimeImmutable $at): StatementPeriod
    {
        return ($this->agreements->agreementAt($consignor, $at)->frequency ?? StatementFrequency::Monthly)->lastClosedPeriod($at);
    }

    /**
     * Units given back, and the entries with money in the currency: sold, returned by a customer, bought out.
     */
    private function listed(LedgerEntry $entry, Currency $currency): bool
    {
        return EntryKind::GivenBack === $entry->kind || $entry->margin()?->revenue->currency->code === $currency->code;
    }

    /**
     * @return list<HeldUnits> the oldest receipt first
     */
    private function held(string $consignor, DateTimeImmutable $end): array
    {
        $byLayer = $this->ledger->heldBy($consignor, $end, ConsignorStatement::MAX_HELD + 1);

        if (\count($byLayer) > ConsignorStatement::MAX_HELD) {
            throw ConsignmentRefused::tooManyLines($consignor, ConsignorStatement::MAX_HELD);
        }

        $held = [];

        foreach ($byLayer as $id => $units) {
            $layer = $this->ledger->layer($id);

            if ($layer instanceof StockLayer) {
                $held[] = HeldUnits::at($layer->id, $layer->sku, $layer->location, $units, $layer->receivedAt(), $end);
            }
        }

        usort($held, static fn (HeldUnits $left, HeldUnits $right): int => [$left->receivedAt, $left->layer] <=> [$right->receivedAt, $right->layer]);

        return $held;
    }
}
