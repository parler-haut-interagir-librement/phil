<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Phil\Markup\Reader\Markdown\Inline;
use Phil\Ujo\Type\TableGrid;

/**
 * The data of a `table` block from the cells a reader found: the plain form, a grid of texts, when no cell spans,
 * aligns or is a header outside the heading row and the table has no caption; the rich form otherwise. Every row is
 * made as wide as the widest, its spans counted.
 *
 * @internal
 */
final class Tables
{
    /** The most columns a cell spans. */
    public const int MAX_COLSPAN = 100;

    /** The most rows a cell spans. */
    public const int MAX_ROWSPAN = 1000;

    /**
     * @param list<list<array{text: Inline, header: bool, colspan?: int, rowspan?: int, align?: string}>> $rows
     *
     * @return array{withHeadings: bool, caption?: Inline, content: list<list<Inline|array<string, mixed>>>}
     */
    public static function data(array $rows, bool $headed, ?Inline $caption = null): array
    {
        $rich = $caption instanceof Inline || array_any($rows, static fn (array $cells, int $index): bool => array_any($cells, static fn (array $cell): bool => \count($cell) > 2 || ($cell['header'] && (!$headed || 0 !== $index))));
        $content = [];

        foreach ($rows as $index => $cells) {
            $content[] = array_map(static fn (array $cell): array|Inline => $rich ? [
                'text' => $cell['text'],
                ...array_diff_key($cell, ['text' => true, 'header' => true]),
                ...$cell['header'] && (!$headed || 0 !== $index) ? ['header' => true] : [],
            ] : $cell['text'], $cells);
        }

        $layout = TableGrid::layout($content);
        $width = [] === $layout ? 0 : max(array_column($layout, 0));

        foreach ($content as $index => $cells) {
            $content[$index] = [...$cells, ...array_fill(0, $width - $layout[$index][0], Inline::html(''))];
        }

        return ['withHeadings' => $headed, ...$caption instanceof Inline ? ['caption' => $caption] : [], 'content' => $content];
    }

    /**
     * The spans and alignment of a cell from the values written for it, each kept when valid: spans as whole numbers
     * capped at their maximum, an alignment among `left`, `center` and `right`.
     *
     * @return array{colspan?: int, rowspan?: int, align?: string}
     */
    public static function layout(?string $colspan, ?string $rowspan, ?string $align): array
    {
        $columns = filter_var($colspan, \FILTER_VALIDATE_INT);
        $rows = filter_var($rowspan, \FILTER_VALIDATE_INT);
        $align = strtolower(trim($align ?? ''));

        return [
            ...false !== $columns && $columns > 1 ? ['colspan' => min($columns, self::MAX_COLSPAN)] : [],
            ...false !== $rows && $rows > 1 ? ['rowspan' => min($rows, self::MAX_ROWSPAN)] : [],
            ...\in_array($align, ['left', 'center', 'right'], true) ? ['align' => $align] : [],
        ];
    }
}
