<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

/**
 * The columns the cells of a table take, as a browser lays them out: a cell goes to the first column of its row no
 * cell above still covers, and covers `colspan` columns of `rowspan` rows. A cell is a text, which covers one, or an
 * object with `colspan` and `rowspan`.
 */
final class TableGrid
{
    /**
     * Each row's width, the number of columns its cells and the cells above cover, and the column of each of its cells.
     *
     * @param array<array-key, mixed> $rows
     *
     * @return list<array{int, list<int>}>
     */
    public static function layout(array $rows): array
    {
        /** @var array<int, int> $carry each column, to the rows still covered from above, this one included */
        $carry = [];
        $layout = [];

        foreach ($rows as $row) {
            $column = 0;
            $positions = [];
            $next = [];

            foreach (\is_array($row) ? $row : [] as $cell) {
                while (isset($carry[$column])) {
                    ++$column;
                }

                $positions[] = $column;
                $span = self::span($cell, 'colspan');
                $down = self::span($cell, 'rowspan');

                for ($covered = $column; $covered < $column + $span && $down > 1; ++$covered) {
                    $next[$covered] = $down - 1;
                }

                $column += $span;
            }

            $layout[] = [max([$column, ...array_map(static fn (int $covered): int => $covered + 1, array_keys($carry))]), $positions];

            foreach ($carry as $covered => $left) {
                if ($left > 1) {
                    $next[$covered] ??= $left - 1;
                }
            }

            $carry = $next;
        }

        return $layout;
    }

    /**
     * The columns or rows a cell covers.
     */
    public static function span(mixed $cell, string $direction): int
    {
        $span = \is_array($cell) ? $cell[$direction] ?? null : null;

        return \is_int($span) ? max($span, 1) : 1;
    }
}
