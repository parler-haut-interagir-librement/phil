<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application\Export;

use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\HeldUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementLine;

/**
 * A statement as plain data for a template — `@PhilCommerceConsignment/statement.html.twig`, or the application's own —:
 * strings and integers only, amounts in decimal with their currency, instants in UTC.
 */
final readonly class StatementView
{
    public const string TEMPLATE = '@PhilCommerceConsignment/statement.html.twig';

    /**
     * @return array{number: string, consignor: string, currency: string, period_start: string, period_end: string, issued_at: string, units_sold: int, units_returned: int, units_bought_out: int, units_given_back: int, units_held: int, revenue: string, share: string, commission: string, lines: list<array<string, int|string>>, held: list<array<string, int|string>>}
     */
    public function data(ConsignorStatement $statement): array
    {
        $period = $statement->period();

        return [
            'number' => $statement->number,
            'consignor' => $statement->consignor,
            'currency' => $statement->currency()->code,
            'period_start' => $period->start->format('Y-m-d\TH:i:s\Z'),
            'period_end' => $period->end->format('Y-m-d\TH:i:s\Z'),
            'issued_at' => $statement->issuedAt()->format('Y-m-d\TH:i:s\Z'),
            'units_sold' => $statement->unitsSold,
            'units_returned' => $statement->unitsReturned,
            'units_bought_out' => $statement->unitsBoughtOut,
            'units_given_back' => $statement->unitsGivenBack,
            'units_held' => $statement->unitsHeld(),
            'revenue' => $statement->revenue()->toDecimal(),
            'share' => $statement->share()->toDecimal(),
            'commission' => $statement->commission()->toDecimal(),
            'lines' => array_map(static fn (StatementLine $line): array => [
                'moved_at' => $line->movedAt->format('Y-m-d\TH:i:s\Z'),
                'reference' => $line->reference ?? '',
                'sku' => $line->sku,
                'location' => $line->location,
                'kind' => $line->kind->value,
                'units' => $line->units,
                'revenue' => $line->revenue->toDecimal(),
                'share' => $line->share->toDecimal(),
                'commission' => $line->commission->toDecimal(),
            ], $statement->lines()),
            'held' => array_map(static fn (HeldUnits $units): array => [
                'sku' => $units->sku,
                'location' => $units->location,
                'units' => $units->units,
                'received_at' => $units->receivedAt->format('Y-m-d\TH:i:s\Z'),
                'age_in_days' => $units->ageInDays,
            ], $statement->held()),
        ];
    }
}
