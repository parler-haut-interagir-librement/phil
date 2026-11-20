<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

/**
 * What a printer receives for an order: its number and one line per product printed alike, written as JSON for a
 * program and as CSV for a spreadsheet, beside the print files the lines name.
 */
final readonly class ProductionOrder
{
    /** The version of the JSON form, written in it. */
    public const int VERSION = 1;

    /**
     * @param list<ProductionLine> $lines in the order of the order's lines
     */
    public function __construct(
        public string $orderNumber,
        public array $lines,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->lines;
    }

    /**
     * How many units are printed, every line together.
     */
    public function units(): int
    {
        return array_sum(array_map(static fn (ProductionLine $line): int => $line->quantity, $this->lines));
    }

    public function json(): string
    {
        return json_encode([
            'version' => self::VERSION,
            'order' => $this->orderNumber,
            'lines' => array_map(static fn (ProductionLine $line): array => $line->toArray(), $this->lines),
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION)."\n";
    }

    /**
     * One row per line: the order, the SKU, the quantity, one column per axis — its code, and the supplier's name in
     * brackets —, the language version, the dice, the options and the print files. A cell that a spreadsheet would read
     * as a formula starts with an apostrophe, since an option may hold what a buyer typed.
     */
    public function csv(): string
    {
        $axes = [];

        foreach ($this->lines as $line) {
            foreach (array_keys($line->values) as $axis) {
                $axes[$axis] = $axis;
            }
        }

        $rows = [['order', 'sku', 'quantity', ...array_values($axes), 'language', 'dice', 'options', 'files']];

        foreach ($this->lines as $line) {
            $row = [$this->orderNumber, $line->sku, (string) $line->quantity];

            foreach ($axes as $axis) {
                $value = $line->values[$axis] ?? null;
                $row[] = null === $value ? '' : $value['code'].(null === $value['supplier'] ? '' : ' ('.$value['supplier'].')');
            }

            $row[] = $line->language ?? '';
            $row[] = $this->pairs($line->dice);
            $row[] = $this->pairs($line->options);
            $row[] = implode(' ', array_map(static fn (PrintFile $file): string => $file->path, $line->files));
            $rows[] = $row;
        }

        return implode('', array_map(static fn (array $row): string => implode(',', array_map(self::cell(...), $row))."\r\n", $rows));
    }

    /**
     * @param array<string, string> $pairs
     */
    private function pairs(array $pairs): string
    {
        return implode(' | ', array_map(static fn (string $code, string $value): string => $code.': '.$value, array_keys($pairs), $pairs));
    }

    private static function cell(string $cell): string
    {
        if (1 === preg_match('/^[=+\-@\t\r]/', $cell)) {
            $cell = "'".$cell;
        }

        return 1 === preg_match('/[",\r\n]/', $cell) ? '"'.str_replace('"', '""', $cell).'"' : $cell;
    }
}
