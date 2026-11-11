<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Tables;
use Phil\Ujo\Block;

/**
 * The blocks the wikis write each their own way and UJO keeps one way: headings, code, lists — a task list when every
 * item starts with a box, `[ ]` or `[x]` —, tables, definition lists; and the lines two wikis share: `;` terms and `:`
 * definitions or indented paragraphs, in MediaWiki and Creole, and `>` quotes, in DokuWiki and Creole.
 *
 * @internal
 */
final class Structures
{
    /** The cells a row keeps; the content of those after the last is joined to it. */
    private const int MAX_CELLS = 64;

    /** The deepest indentation of a paragraph. */
    private const int MAX_INDENT = 8;

    public static function heading(Engine $engine, int $level, string $text): Block
    {
        return $engine->block('header', ['text' => $engine->inline($text), 'level' => $level]);
    }

    /**
     * A code block, its language kept when it is a name a highlighter knows.
     */
    public static function code(Engine $engine, string $code, string $language): Block
    {
        $valid = '' !== $language && \strlen($language) <= 32 && strspn($language, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_+#.-') === \strlen($language);

        return $engine->block('code', ['code' => $code, ...$valid ? ['language' => $language] : []]);
    }

    /**
     * A list of entries, each its level — 1 for the items of the list — and its text; an entry deeper than the one
     * before it by more than a level is an item of that one. The items open are kept on a stack, the innermost made an
     * item of the one around it when an entry of a lower level comes, in place, so that a list is built in linear
     * time.
     *
     * @param non-empty-list<array{int, string}> $entries
     */
    public static function listing(Engine $engine, bool $ordered, array $entries): Block
    {
        $checklist = array_all($entries, static fn (array $entry): bool => null !== self::box($entry[1]));
        $items = [];
        $open = [];
        $children = [];

        foreach ($entries as [$level, $text]) {
            while ([] !== $open && \count($open) >= $level) {
                self::fold($open, $children, $items);
            }

            $open[] = [$engine->inline($checklist ? substr(ltrim($text), 3) : $text), $checklist ? ['checked' => self::box($text)] : []];
            $children[] = [];
        }

        while ([] !== $open) {
            self::fold($open, $children, $items);
        }

        return $engine->block('list', ['style' => match (true) {
            $checklist => 'checklist',
            $ordered => 'ordered',
            default => 'unordered',
        }, 'meta' => [], 'items' => $items]);
    }

    /**
     * A table of rows of cells, each a flag — a heading —, its text and its spans and alignment, its rows made as long
     * as the longest; a first row of headings only is its headings, a heading elsewhere a header cell. A row keeps
     * sixty-four cells, the text of those after the last joined to it. The caption, when given, is read as inline
     * content.
     *
     * @param list<list<array{0: bool, 1: string, 2?: array{colspan?: int, rowspan?: int, align?: string}}>> $rows
     */
    public static function table(Engine $engine, array $rows, string $caption = ''): ?Block
    {
        $rows = array_values(array_filter($rows, static fn (array $row): bool => [] !== $row));

        if ([] === $rows) {
            return null;
        }

        $content = [];

        foreach ($rows as $row) {
            $kept = \array_slice($row, 0, self::MAX_CELLS - 1);
            $joined = \array_slice($row, self::MAX_CELLS - 1);
            $cells = [] === $joined ? $kept : [...$kept, [$joined[0][0], implode(' ', array_column($joined, 1)), $joined[0][2] ?? []]];
            $content[] = array_map(static fn (array $cell): array => ['text' => $engine->inline($cell[1]), 'header' => $cell[0], ...$cell[2] ?? []], $cells);
        }

        return $engine->block('table', Tables::data($content, array_all($rows[0], static fn (array $cell): bool => $cell[0]), '' === trim($caption) ? null : $engine->inline(trim($caption))));
    }

    /**
     * A definition list of terms, each with its definitions.
     *
     * @param list<string>       $terms
     * @param list<list<string>> $definitions the definitions of each term
     */
    public static function definitions(Engine $engine, array $terms, array $definitions): Block
    {
        return $engine->block('definitionList', ['items' => array_map(static fn (string $term, array $given): array => [
            'term' => $engine->inline($term),
            'definitions' => [] === $given ? [Inline::html('')] : array_map($engine->inline(...), $given),
        ], $terms, $definitions)]);
    }

    /**
     * The lines that start with `;` or `:`: a term, `; Term`, its definition on the same line after ` : ` or on the
     * lines after it, `: Definition`; a line of `:` that follows no term is a paragraph indented by as many levels.
     *
     * @return list<Block>
     */
    public static function terms(Engine $engine): array
    {
        $blocks = [];
        $terms = [];
        $definitions = [];

        for ($line = $engine->line(); null !== $line && \in_array($line[0] ?? '', [';', ':'], true); $line = $engine->line()) {
            $engine->advance();
            $marks = strspn($line, ':');
            $text = substr($line, ';' === $line[0] ? 1 : $marks);
            $split = ';' === $line[0] ? explode(' : ', $text, 2) : [];

            if ([] !== $split) {
                $terms[] = $split[0];
                $definitions[] = isset($split[1]) ? [$split[1]] : [];
            } elseif ([] !== $terms) {
                $definitions[\count($terms) - 1][] = $text;
            } else {
                $blocks[] = $engine->block('paragraph', ['text' => $engine->inline($text)], ['indent' => ['indentLevel' => min($marks, self::MAX_INDENT)]]);
            }
        }

        return [] === $terms ? $blocks : [...$blocks, self::definitions($engine, $terms, $definitions)];
    }

    /**
     * The lines that start with `>`: a quote, each line on its own, the levels of a quote inside a quote read as one.
     *
     * @return list<Block>
     */
    public static function quote(Engine $engine): array
    {
        $lines = [];

        for ($line = $engine->line(); null !== $line && str_starts_with($line, '>'); $line = $engine->line()) {
            $engine->advance();
            $lines[] = trim($engine->spans->parse(ltrim($line, '> ')), " \t\n");
        }

        return [$engine->block('quote', ['text' => Inline::html(implode('<br>', $lines)), 'caption' => Inline::html('')])];
    }

    /**
     * The cells of a row of a table written on one line, each the separator that opens it and its text: a separator
     * inside a link or an image, `[[…]]` or `{{…}}`, is part of the cell, and the separator that ends the line opens
     * no cell.
     *
     * @return list<array{string, string}>
     */
    public static function cells(string $line, string $separators): array
    {
        $cursor = new Cursor($line);
        $cells = [];
        $missing = [];

        while (!$cursor->isEnd()) {
            $separator = $cursor->advance();
            $stops = \sprintf('[{%s', $separators);
            $text = $cursor->consumeUntil($stops);

            while (!str_contains($separators, $cursor->current())) {
                $pair = $cursor->advance(2);
                $close = ['[[' => ']]', '{{' => '}}'][$pair] ?? null;
                $inner = null === $close || \in_array($close, $missing, true) ? null : $cursor->consumeThrough($close);
                $missing = null === $inner && null !== $close ? [...$missing, $close] : $missing;
                $cursor->moveTo($cursor->position() - (null === $inner ? \strlen($pair) - 1 : 0));
                $text .= (null === $inner ? substr($pair, 0, 1) : $pair.$inner.$close);
            }

            $cells[] = [$separator, $text];
        }

        $last = array_key_last($cells);

        return null !== $last && '' === trim($cells[$last][1]) ? \array_slice($cells, 0, -1) : $cells;
    }

    /**
     * A box at the start of a task: `false` for `[ ]`, `true` for `[x]`; `null` when the text starts with none.
     */
    private static function box(string $text): ?bool
    {
        $text = ltrim($text);
        $box = substr($text, 0, 3);

        if (!\in_array($box, ['[ ]', '[x]', '[X]'], true) || !\in_array($text[3] ?? ' ', [' ', "\t"], true)) {
            return null;
        }

        return '[ ]' !== $box;
    }

    /**
     * The innermost item open made an item of the one around it, or of the list, in place.
     *
     * @param non-empty-list<array{Inline, array<string, bool|null>}> $open     the content and meta of each item open
     * @param list<list<mixed>>                                       $children the items of each item open
     * @param list<mixed>                                             $items    the items of the list
     *
     * @param-out list<array{Inline, array<string, bool|null>}> $open
     * @param-out list<list<mixed>>                            $children
     */
    private static function fold(array &$open, array &$children, array &$items): void
    {
        [$content, $meta] = array_pop($open);
        $item = ['content' => $content, 'meta' => $meta, 'items' => array_pop($children) ?? []];

        if ([] === $children) {
            $items[] = $item;
        } else {
            $children[\count($children) - 1][] = $item;
        }
    }
}
