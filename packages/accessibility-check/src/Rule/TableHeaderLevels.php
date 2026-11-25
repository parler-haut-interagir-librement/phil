<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Dom\Element;
use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `table-header-levels`: a table of data has one level of column headers. Screen readers announce a header cell that
 * spans several columns or rows, or a second row of column headers, unevenly — some read only its first part, some
 * the wrong axis, phones often none —, and no automatic tool hears the difference. A header cell, a `th` or a cell of
 * role `columnheader` or `rowheader`, with a `colspan` or a `rowspan` above 1, or a `rowspan` of 0, is reported; so is
 * a `thead` with more than one row holding header cells. A group of columns becomes a table of its own with its
 * `caption`, or one more column. A table of role `presentation` or `none` lays out and is left alone. A UJO `table`
 * block has no merged cells; the tables of its HTML fields are read as HTML.
 */
final class TableHeaderLevels extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'table-header-levels';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::InfoAndRelationships;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Warning;
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkHtml(HtmlTarget $target): iterable
    {
        foreach ($target->elements('th', 'td', 'thead') as $element) {
            $table = $element->closest('table');

            if (null === $table || $target->isHidden($element) || $target->hasRole($table, 'presentation', 'none')) {
                continue;
            }

            if ('thead' === $element->localName) {
                $rows = $this->headerRows($element, $target);

                if ($rows > 1) {
                    yield new Violation($target->locate($element), new Message('table-header-levels.rows', ['rows' => $rows]));
                }

                continue;
            }

            if (!$this->isHeader($element, $target)) {
                continue;
            }

            foreach (['colspan', 'rowspan'] as $attribute) {
                $span = $this->span($element->getAttribute($attribute) ?? '', 'rowspan' === $attribute);

                if (null !== $span) {
                    yield new Violation($target->locate($element), new Message('table-header-levels.merged', ['attribute' => $attribute, 'span' => $span]));

                    break;
                }
            }
        }
    }

    /**
     * A UJO `table` block holds rows of plain cells, none merged.
     *
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        return [];
    }

    private function isHeader(Element $cell, HtmlTarget $target): bool
    {
        return 'th' === $cell->localName || $target->hasRole($cell, 'columnheader', 'rowheader');
    }

    /**
     * The rows of a `thead`, its own children, that hold a header cell of their own.
     */
    private function headerRows(Element $thead, HtmlTarget $target): int
    {
        $rows = 0;

        foreach ($thead->children as $row) {
            if ('tr' !== $row->localName) {
                continue;
            }

            foreach ($row->children as $cell) {
                if ($this->isHeader($cell, $target)) {
                    ++$rows;

                    break;
                }
            }
        }

        return $rows;
    }

    /**
     * The span, read as HTML reads a non-negative integer — leading white space, an optional plus sign, the digits up
     * to the first other character —, when it joins more than one column or row; null when it joins one. A `rowspan`
     * of 0 runs to the end of its group; a span HTML cannot read, or a `colspan` of 0, is 1.
     */
    private function span(string $value, bool $row): ?string
    {
        if (1 !== preg_match('/^[\t\n\f\r ]*\+?(\d+)/', $value, $match)) {
            return null;
        }

        $digits = ltrim($match[1], '0');

        return match ($digits) {
            '' => $row ? '0' : null,
            '1' => null,
            default => $digits,
        };
    }
}
