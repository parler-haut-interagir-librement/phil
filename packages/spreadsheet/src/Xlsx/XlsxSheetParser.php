<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Xlsx;

use DateTimeImmutable;
use Generator;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Internal\RowBuilder;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Row;
use XMLReader;

/**
 * Reads the rows of an XLSX worksheet part node by node: the `<row>` and `<c>` elements, the value of each cell by its
 * type, the shared texts by their index, the serial numbers under a date format as dates. Formulas are not run: a
 * cell with one gives the value the spreadsheet saved with it.
 *
 * One instance reads one part: it holds the state of the row and of the cell being read.
 *
 * @internal
 */
final class XlsxSheetParser
{
    /** The nodes that hold text: a comment or a processing instruction inside a value is no part of it. */
    private const array TEXT_NODES = [XMLReader::TEXT, XMLReader::CDATA, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE];

    private readonly RowBuilder $builder;

    private int $rowNumber = 0;

    /** @var array<int, Cell>|null the cells of the row being read, null outside a row */
    private ?array $cells = null;

    private int $nextColumn = 0;

    private int $column = 0;

    private string $type = 'n';

    private int $style = 0;

    private ?string $value = null;

    private ?string $inline = null;

    private bool $inCell = false;

    private int $phonetic = 0;

    /** Whether the text nodes read belong to the value of the cell, or to its inline text. */
    private bool $inValue = false;

    private bool $inText = false;

    /** @var array<string, int> the column of each letters met, from 1: a reference is parsed once per read */
    private array $columns = [];

    /**
     * @param list<string>         $sharedStrings the shared texts of the workbook
     * @param array<int, CellType> $dateStyles    the kind of date of each style that shows one
     */
    public function __construct(
        private readonly array $sharedStrings,
        private readonly array $dateStyles,
        private readonly bool $date1904,
        private readonly ReadLimits $limits,
        private readonly string $part,
    ) {
        $this->builder = new RowBuilder($limits);
    }

    /**
     * @return Generator<int, Row>
     *
     * @throws ReadRefused when the part is unsafe, malformed or over a limit
     */
    public function rows(SafeXmlReader $reader): Generator
    {
        $xml = $reader->xml;

        while ($reader->read()) {
            $type = $xml->nodeType;

            if (XMLReader::ELEMENT === $type) {
                $this->start($xml);
            } elseif (XMLReader::END_ELEMENT === $type) {
                $row = $this->end($xml->localName);

                if ($row instanceof Row) {
                    yield $row;
                }
            } elseif (\in_array($type, self::TEXT_NODES, true)) {
                $this->text($xml->value);
            }
        }
    }

    private function start(XMLReader $xml): void
    {
        switch ($xml->localName) {
            case 'row':
                $this->rowNumber = $this->rowNumber($xml->getAttribute('r'));
                $this->cells = $xml->isEmptyElement ? null : [];
                $this->nextColumn = 0;

                break;

            case 'c':
                $this->startCell($xml);

                break;

            case 'v':
                $this->value = $this->inCell ? '' : null;
                $this->inValue = $this->inCell && !$xml->isEmptyElement;

                break;

            case 'is':
                $this->inline = '';

                break;

            case 'rPh':
                $this->phonetic += $xml->isEmptyElement ? 0 : 1;

                break;

            case 't':
                $this->inText = null !== $this->inline && 0 === $this->phonetic && !$xml->isEmptyElement;

                break;
        }
    }

    private function text(string $text): void
    {
        if ($this->inValue) {
            $this->value .= $text;
        } elseif ($this->inText) {
            $this->inline .= $text;
        }
    }

    private function startCell(XMLReader $xml): void
    {
        if (null === $this->cells) {
            throw ReadRefused::malformed($this->part, 'has a cell outside a row');
        }

        $this->column = $this->column($xml->getAttribute('r'));
        $this->nextColumn = $this->column + 1;
        $this->type = $xml->getAttribute('t') ?? 'n';
        $this->style = (int) $xml->getAttribute('s');
        $this->value = null;
        $this->inline = null;
        $this->inCell = !$xml->isEmptyElement;
    }

    private function end(string $name): ?Row
    {
        $this->inValue = false;
        $this->inText = false;

        if ('rPh' === $name) {
            --$this->phonetic;
        } elseif ('c' === $name && $this->inCell && null !== $this->cells) {
            $this->cells[$this->column] = $this->cell();
            $this->inCell = false;
        } elseif ('row' === $name) {
            $row = $this->builder->row($this->rowNumber, $this->cells ?? []);
            $this->cells = null;

            return $row;
        }

        return null;
    }

    private function rowNumber(?string $attribute): int
    {
        if (null === $attribute) {
            return $this->rowNumber + 1;
        }

        if (1 !== preg_match('/^[1-9]\d{0,9}$/D', $attribute)) {
            throw ReadRefused::malformed($this->part, 'has a row without a number');
        }

        return (int) $attribute;
    }

    private function column(?string $reference): int
    {
        if (null === $reference) {
            $column = $this->nextColumn;
        } else {
            $letters = rtrim($reference, '0123456789');
            $column = ($this->columns[$letters] ??= CellReference::column($letters) ?? throw ReadRefused::malformed($this->part, 'has a cell without a reference')) - 1;
        }

        if ($column >= $this->limits->maxColumns) {
            throw ReadRefused::overLimit('The column of a cell', $this->limits->maxColumns);
        }

        return $column;
    }

    private function cell(): Cell
    {
        $value = $this->value;

        return match ($this->type) {
            's' => Cell::text($this->shared($value)),
            'inlineStr' => Cell::text(XlsxText::unescape($this->inline ?? '')),
            'str', 'e' => Cell::text(XlsxText::unescape($value ?? '')),
            'b' => null === $value || '' === $value ? Cell::empty() : Cell::boolean('1' === $value || 'true' === $value),
            'd' => null === $value || '' === $value ? Cell::empty() : (Values::isoDate($value) ?? throw ReadRefused::malformed($this->part, 'has a date cell that holds no date')),
            'n' => $this->number($value),
            default => throw ReadRefused::malformed($this->part, 'has a cell of an unknown type'),
        };
    }

    private function shared(?string $index): string
    {
        $text = null !== $index && 1 === preg_match('/^\d{1,10}$/D', $index) ? ($this->sharedStrings[(int) $index] ?? null) : null;

        return $text ?? throw ReadRefused::malformed($this->part, 'refers to a shared text it does not have');
    }

    private function number(?string $value): Cell
    {
        if (null === $value || '' === $value) {
            return Cell::empty();
        }

        $number = Values::parseNumber(trim($value)) ?? throw ReadRefused::malformed($this->part, 'has a number cell that holds no number');
        $kind = $this->dateStyles[$this->style] ?? null;
        $date = null === $kind ? null : ExcelDate::date((float) $number, $this->date1904);

        return match (true) {
            !$date instanceof DateTimeImmutable => Cell::number($number),
            CellType::Date === $kind => Cell::date($date),
            default => Cell::dateTime($date),
        };
    }
}
