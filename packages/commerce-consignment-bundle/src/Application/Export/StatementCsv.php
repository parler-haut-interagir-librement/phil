<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application\Export;

use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementLine;
use RuntimeException;

/**
 * A statement as CSV, one row per line then a row of totals, amounts in decimal: for a spreadsheet, for the consignor's
 * accounting. A text cell that a spreadsheet would read as a formula starts with an apostrophe.
 */
final readonly class StatementCsv
{
    public const array HEADER = ['statement', 'consignor', 'currency', 'moved_at', 'reference', 'sku', 'location', 'kind', 'units', 'revenue', 'share', 'commission'];

    public function write(ConsignorStatement $statement): string
    {
        $stream = fopen('php://memory', 'w+');

        if (false === $stream) {
            throw new RuntimeException('The memory stream of the CSV did not open.');
        }

        fputcsv($stream, self::HEADER, escape: '');
        $head = [$this->text($statement->number), $this->text($statement->consignor), $statement->currency()->code];

        foreach ($statement->lines() as $line) {
            fputcsv($stream, [...$head, ...$this->row($line)], escape: '');
        }

        fputcsv($stream, [
            ...$head,
            $statement->period()->end->format('Y-m-d\TH:i:s\Z'),
            'total',
            '',
            '',
            '',
            $statement->unitsSold - $statement->unitsReturned + $statement->unitsBoughtOut,
            $statement->revenue()->toDecimal(),
            $statement->share()->toDecimal(),
            $statement->commission()->toDecimal(),
        ], escape: '');
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * @return list<int|string>
     */
    private function row(StatementLine $line): array
    {
        return [
            $line->movedAt->format('Y-m-d\TH:i:s\Z'),
            $this->text($line->reference ?? ''),
            $this->text($line->sku),
            $this->text($line->location),
            $line->kind->value,
            $line->units,
            $line->revenue->toDecimal(),
            $line->share->toDecimal(),
            $line->commission->toDecimal(),
        ];
    }

    /**
     * A text a spreadsheet would read as a formula, made plain text. The identifiers of the ledger hold no control
     * character: a tab or a carriage return never starts one.
     */
    private function text(string $value): string
    {
        return 1 === preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
