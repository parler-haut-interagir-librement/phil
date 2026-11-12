<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `table-headers`: a table of data marks its header cells, so that a screen reader announces, cell by cell, the
 * heading it belongs to. In HTML, a table of two rows or more holds a `th`, or a cell of role `columnheader` or
 * `rowheader`; a table of role `presentation` or `none` lays out and is left alone, as are the cells of a table nested
 * in it. In UJO, a `table` block of two rows or more has its heading row turned on, or a cell marked `header`.
 */
final class TableHeaders extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'table-headers';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::InfoAndRelationships;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Error;
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkHtml(HtmlTarget $target): iterable
    {
        foreach ($target->elements('table') as $table) {
            if ($target->isHidden($table) || $target->hasRole($table, 'presentation', 'none')) {
                continue;
            }

            $rows = 0;
            $headed = false;

            foreach ($table->querySelectorAll('tr, th, [role]') as $element) {
                if ($element->closest('table') !== $table) {
                    continue;
                }

                $rows += 'tr' === $element->localName ? 1 : 0;
                $headed = $headed || 'th' === $element->localName || $target->hasRole($element, 'columnheader', 'rowheader');
            }

            if ($rows > 1 && !$headed) {
                yield new Violation($target->locate($table), new Message('table-headers.missing'));
            }
        }
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        $block = $visit->block;
        $content = UjoTarget::field($block, 'content');

        if ('table' !== $block->type || !\is_array($content) || \count($content) < 2 || true === UjoTarget::field($block, 'withHeadings')) {
            return;
        }

        foreach ($content as $row) {
            foreach (\is_array($row) ? $row : [] as $cell) {
                if (\is_array($cell) && true === ($cell['header'] ?? null)) {
                    return;
                }
            }
        }

        yield new Violation($visit->locate(), new Message('table-headers.missing'));
    }
}
