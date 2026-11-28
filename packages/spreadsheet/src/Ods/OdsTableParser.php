<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Ods;

use Generator;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Internal\RowBuilder;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Row;
use XMLReader;

/**
 * Reads the rows of one OpenDocument table, from its start element to its end: the rows and cells, their repetitions
 * — an empty cell or row repeated only moves the count, it never fills memory —, the value of each cell by its type,
 * and the text of its paragraphs with their spaces, tabs and line breaks. Annotations, drawings and tables inside a
 * cell are skipped.
 *
 * One instance reads one table: it holds the state of the row and of the cell being read.
 *
 * @internal
 */
final class OdsTableParser
{
    private const string TABLE = OdsContentParser::TABLE;

    private const string OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';

    private const string TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

    private const string DRAWING = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';

    private const array CELLS = ['table-cell', 'covered-table-cell'];

    private readonly RowBuilder $builder;

    private ?int $skipDepth = null;

    private int $rowNumber = 0;

    private int $rowRepeat = 1;

    /** @var array<int, Cell>|null the cells of the row being read, null outside a row */
    private ?array $cells = null;

    private int $column = 0;

    private bool $inCell = false;

    private int $cellRepeat = 1;

    /** @var array<string, string|null> */
    private array $attributes = [];

    private ?string $text = null;

    private int $paragraphDepth = 0;

    private int $paragraphs = 0;

    public function __construct(
        private readonly ReadLimits $limits,
        private readonly string $part,
    ) {
        $this->builder = new RowBuilder($limits);
    }

    /**
     * Reads from the table's start element, where the reader stands, to its end.
     *
     * @return Generator<int, Row>
     */
    public function rows(SafeXmlReader $reader): Generator
    {
        $xml = $reader->xml;
        $tableDepth = $xml->depth;

        while ($reader->read()) {
            $type = $xml->nodeType;

            if (null !== $this->skipDepth) {
                $this->skipDepth = OdsContentParser::stillSkipping($xml, $this->skipDepth);
            } elseif (XMLReader::ELEMENT === $type) {
                $this->inCell ? $this->cellChild($xml) : $this->tableChild($xml);
            } elseif (XMLReader::END_ELEMENT === $type) {
                if ($xml->depth === $tableDepth) {
                    return;
                }

                if ($this->end($xml)) {
                    yield from $this->repeatedRows();
                }
            } elseif ($this->paragraphDepth > 0 && \in_array($type, [XMLReader::TEXT, XMLReader::SIGNIFICANT_WHITESPACE, XMLReader::CDATA], true)) {
                $this->text .= preg_replace('/[ \t\r\n]+/', ' ', $xml->value);
            }
        }
    }

    private function tableChild(XMLReader $xml): void
    {
        if (self::TABLE !== $xml->namespaceURI) {
            return;
        }

        if ('table-row' === $xml->localName) {
            $this->rowRepeat = $this->repeat($xml->getAttributeNs('number-rows-repeated', self::TABLE));
            $this->cells = [];
            $this->column = 0;

            if ($xml->isEmptyElement) {
                $this->rowNumber += $this->rowRepeat;
                $this->cells = null;
            }
        } elseif (null !== $this->cells && \in_array($xml->localName, self::CELLS, true)) {
            $this->cellRepeat = $this->repeat($xml->getAttributeNs('number-columns-repeated', self::TABLE));
            $type = $xml->getAttributeNs('value-type', self::OFFICE);
            // Only the attribute that holds the value of that type is read.
            $this->attributes = ['type' => $type, 'value' => match ($type) {
                'float', 'percentage', 'currency' => $xml->getAttributeNs('value', self::OFFICE),
                'date' => $xml->getAttributeNs('date-value', self::OFFICE),
                'time' => $xml->getAttributeNs('time-value', self::OFFICE),
                'boolean' => $xml->getAttributeNs('boolean-value', self::OFFICE),
                'string' => $xml->getAttributeNs('string-value', self::OFFICE),
                default => null,
            }];
            $this->text = null;
            $this->paragraphDepth = 0;
            $this->paragraphs = 0;
            $this->inCell = !$xml->isEmptyElement;

            if ($xml->isEmptyElement) {
                $this->place();
            }
        }
    }

    private function cellChild(XMLReader $xml): void
    {
        $namespace = $xml->namespaceURI;
        $name = $xml->localName;

        if (self::DRAWING === $namespace || (self::OFFICE === $namespace && 'annotation' === $name) || (self::TABLE === $namespace && 'table' === $name)) {
            $this->skipDepth = $xml->isEmptyElement ? null : $xml->depth;
        } elseif (self::TEXT === $namespace) {
            $this->textElement($name, $xml);
        }
    }

    private function textElement(string $name, XMLReader $xml): void
    {
        if ('p' === $name || 'h' === $name) {
            if (0 === $this->paragraphDepth && $this->paragraphs++ > 0) {
                $this->text .= "\n";
            }

            $this->paragraphDepth += $xml->isEmptyElement ? 0 : 1;
            $this->text ??= '';
        } elseif ($this->paragraphDepth > 0) {
            $this->text .= match ($name) {
                's' => str_repeat(' ', max(1, min((int) ($xml->getAttributeNs('c', self::TEXT) ?? 1), 32767))),
                'tab' => "\t",
                'line-break' => "\n",
                default => '',
            };
        }
    }

    /**
     * Ends the element: true when it ends a row.
     */
    private function end(XMLReader $xml): bool
    {
        $name = $xml->localName;

        if (self::TEXT === $xml->namespaceURI && ('p' === $name || 'h' === $name)) {
            --$this->paragraphDepth;
        } elseif ($this->inCell && self::TABLE === $xml->namespaceURI && \in_array($name, self::CELLS, true)) {
            $this->inCell = false;
            $this->place();
        }

        return !$this->inCell && null !== $this->cells && self::TABLE === $xml->namespaceURI && 'table-row' === $name;
    }

    /**
     * The row read, as many times as it is repeated; an empty row only moves the count.
     *
     * @return Generator<int, Row>
     */
    private function repeatedRows(): Generator
    {
        $cells = $this->cells ?? [];
        $this->cells = null;

        for ($repeat = 0; $repeat < $this->rowRepeat; ++$repeat) {
            $row = $this->builder->row($this->rowNumber + 1, $cells);

            if (!$row instanceof Row) {
                $this->rowNumber += $this->rowRepeat - $repeat;

                return;
            }

            ++$this->rowNumber;

            yield $row;
        }
    }

    private function repeat(?string $attribute): int
    {
        if (null === $attribute) {
            return 1;
        }

        if (1 !== preg_match('/^[1-9]\d{0,9}$/D', $attribute)) {
            throw ReadRefused::malformed($this->part, 'repeats a row or a cell a number of times that is not one');
        }

        return (int) $attribute;
    }

    /**
     * Places the cell read at the column, repeated: an empty cell only moves the column.
     */
    private function place(): void
    {
        $cell = $this->cell();

        if ($cell->isEmpty()) {
            $this->column += $this->cellRepeat;

            return;
        }

        for ($index = 0; $index < $this->cellRepeat; ++$index) {
            if ($this->column >= $this->limits->maxColumns) {
                throw ReadRefused::overLimit('The number of columns of a row', $this->limits->maxColumns);
            }

            $this->cells[$this->column++] = $cell;
        }
    }

    private function cell(): Cell
    {
        $attributes = $this->attributes;

        return match ($attributes['type']) {
            'float', 'percentage', 'currency' => Cell::number(Values::parseNumber((string) $attributes['value']) ?? throw ReadRefused::malformed($this->part, 'has a number cell that holds no number')),
            'date' => Values::isoDate((string) $attributes['value']) ?? throw ReadRefused::malformed($this->part, 'has a date cell that holds no date'),
            'boolean' => Cell::boolean(\in_array($attributes['value'], ['true', '1'], true)),
            'time' => Cell::text($this->text ?? (string) $attributes['value']),
            'string' => Cell::text($attributes['value'] ?? $this->text ?? ''),
            default => Cell::text($this->text ?? ''),
        };
    }
}
