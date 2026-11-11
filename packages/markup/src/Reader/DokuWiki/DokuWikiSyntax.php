<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\DokuWiki;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Tables;
use Phil\Markup\Reader\Wiki\Dialect;
use Phil\Markup\Reader\Wiki\Engine;
use Phil\Markup\Reader\Wiki\Spans;
use Phil\Markup\Reader\Wiki\Structures;
use Phil\Markup\Reader\Wiki\Syntax;
use Phil\Markup\Reader\Wiki\Tag;
use Phil\Markup\Reader\Wiki\Toggle;
use Phil\Markup\Reader\Wiki\WikiLinks;
use Phil\Ujo\Block;

/**
 * The syntax of DokuWiki: headings `====== … ======`, the fewer signs the deeper, `**bold**`, `//italic//`,
 * `__underline__`, `''monospace''`, links `[[page|text]]`, media `{{ image.png?200|Caption }}` placed by their spaces,
 * footnotes `((…))`, `%%nowiki%%`, line breaks `\\`, e-mail addresses `<…>`, lists indented by two spaces, `*` and
 * `-`, tables of `^` headings and `|` cells, quotes `>`, code indented by two spaces, rules `----`; and the extensions
 * in its own style: macros `~~TOC~~`, `~~PAGEBREAK~~`, `~~SPACER:3~~`…, definitions `  = Term : Definition`, and the
 * tags of its plugins, `<note>`, `<columns>`, `<WRAP>`.
 *
 * @internal
 */
final readonly class DokuWikiSyntax implements Syntax
{
    /** The extensions of the files shown as images; any other file is linked. */
    private const array IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'bmp'];

    /** The macros of DokuWiki that leave nothing in the document. */
    private const array SILENT = ['notoc', 'nocache'];

    #[Override]
    public function dialect(): Dialect
    {
        return Dialect::DokuWiki;
    }

    #[Override]
    public function prepare(string $source): string
    {
        return $source;
    }

    #[Override]
    public function triggers(): string
    {
        return "*/_'([{%\\";
    }

    #[Override]
    public function inline(Cursor $cursor, Spans $spans): Toggle|string|null
    {
        return match ($cursor->current()) {
            '*' => $this->toggle($cursor, '**', 'b'),
            '/' => $this->toggle($cursor, '//', 'i'),
            '_' => $this->toggle($cursor, '__', 'u'),
            "'" => $this->toggle($cursor, "''", 'code'),
            '(' => $this->footnote($cursor, $spans),
            '[' => $this->link($cursor, $spans),
            '{' => $this->media($cursor, $spans),
            '%' => $this->nowiki($cursor, $spans),
            '\\' => $this->lineBreak($cursor),
            default => $this->email($cursor, $spans),
        };
    }

    #[Override]
    public function block(Engine $engine, int $depth): ?array
    {
        $line = $engine->line() ?? '';

        return match (true) {
            str_starts_with($line, '==') => $this->heading($engine, $line),
            str_starts_with($line, '----') => $this->rule($engine, $line),
            '^' === $line[0] || '|' === $line[0] => $this->table($engine),
            '>' === $line[0] => Structures::quote($engine),
            str_starts_with($line, '~~') => $this->macro($engine, $line, $depth),
            $this->columns($line) >= 2 => $this->indented($engine),
            default => null,
        };
    }

    private function toggle(Cursor $cursor, string $marks, string $tag): ?Toggle
    {
        return $cursor->consume($marks) ? new Toggle($marks, $tag) : null;
    }

    /**
     * A footnote, `((text))`.
     */
    private function footnote(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $content = $cursor->consume('((') ? $spans->through($cursor, '))') : null;
        $html = null === $content ? null : $spans->footnote('', $content);

        if (null === $html) {
            $cursor->moveTo($start);
        }

        return $html;
    }

    /**
     * Text kept as written, `%%text%%`.
     */
    private function nowiki(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $content = $cursor->consume('%%') ? $spans->through($cursor, '%%') : null;

        if (null === $content) {
            $cursor->moveTo($start);

            return null;
        }

        return Spans::escape($content);
    }

    /**
     * A line break, `\\` followed by a space or the end of the line.
     */
    private function lineBreak(Cursor $cursor): ?string
    {
        if (!$cursor->comesNext('\\\\') || !\in_array($cursor->peek(2), ['', ' ', "\t", "\n"], true)) {
            return null;
        }

        $cursor->advance(2);
        $cursor->consumeWhile(" \t");

        return '<br>';
    }

    /**
     * An e-mail address between angle brackets, `<name@example.com>`.
     */
    private function email(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $cursor->advance();
        $address = $spans->through($cursor, '>');

        if (null === $address || false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
            $cursor->moveTo($start);

            return null;
        }

        return $spans->link('mailto:'.$address, Spans::escape($address));
    }

    /**
     * A link, `[[page]]`, `[[namespace:page#section|text]]`, `[[https://…|text]]`, `[[name@example.com]]`; a link
     * between wikis, `[[wp>Name]]`, keeps its text alone, as no other wiki is known.
     */
    private function link(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $inside = $cursor->consume('[[') ? $spans->balanced($cursor, '[[', ']]') : null;

        if (null === $inside || str_contains($inside, "\n")) {
            $cursor->moveTo($start);

            return null;
        }

        $parts = explode('|', $inside, 2);
        $target = trim($parts[0]);
        $html = $spans->parse($parts[1] ?? $target, true);

        return match (true) {
            false !== filter_var($target, \FILTER_VALIDATE_EMAIL) => $spans->link('mailto:'.$target, $html),
            WikiLinks::isAddress($target) => $spans->link($target, $html),
            str_contains($target, '>') => $html,
            default => $spans->link($spans->links->page(strtolower($target)), $html),
        };
    }

    /**
     * A file, `{{namespace:image.png?200x100|Caption}}`: an image, on the right when a space opens the braces, on the
     * left when one closes them, centered between two; any other file a link to it.
     */
    private function media(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $inside = $cursor->consume('{{') ? $spans->balanced($cursor, '{{', '}}') : null;

        if (null === $inside || str_contains($inside, "\n")) {
            $cursor->moveTo($start);

            return null;
        }

        $parts = explode('|', $inside, 2);
        $right = str_starts_with($parts[0], ' ');
        $left = str_ends_with($parts[0], ' ');
        $query = explode('?', trim($parts[0]), 2);
        $caption = InlineParser::text($spans->parse(trim($parts[1] ?? ''), true));
        $address = $spans->links->media($query[0]);
        $extension = strtolower(pathinfo($query[0], \PATHINFO_EXTENSION));

        if (!\in_array($extension, self::IMAGES, true)) {
            return $spans->link($address, Spans::escape('' === $caption ? $query[0] : $caption));
        }

        $tokens = match (true) {
            $right && $left => ['text-align:center'],
            $right => ['wrapRight'],
            $left => ['wrapLeft'],
            default => [],
        };

        foreach (explode('&', $query[1] ?? '') as $option) {
            $size = explode('x', $option, 2);

            if (array_all($size, static fn (string $value): bool => ctype_digit($value))) {
                $tokens = [...$tokens, 'width:'.$size[0].'px', ...isset($size[1]) ? ['height:'.$size[1].'px'] : []];
            }
        }

        return $spans->image($address, '', implode(' ', $tokens), $caption);
    }

    /**
     * A heading, `== Title ==` to `====== Title ======`: six signs are the first level, two the fifth.
     *
     * @return list<Block>|null
     */
    private function heading(Engine $engine, string $line): ?array
    {
        $line = rtrim($line, " \t");
        $signs = min(strspn($line, '='), strspn(strrev($line), '='));
        $text = trim(substr($line, $signs, \strlen($line) - 2 * $signs));

        if ($signs < 2 || '' === $text) {
            return null;
        }

        $engine->advance();

        return [Structures::heading($engine, 7 - min($signs, 6), $text)];
    }

    /**
     * @return list<Block>
     */
    private function rule(Engine $engine, string $line): array
    {
        $rest = ltrim(substr($line, strspn($line, '-')), " \t");
        '' === $rest ? $engine->advance() : $engine->replace($rest);

        return [$engine->block('delimiter', [])];
    }

    /**
     * A table: each line a row, `^` opening a heading cell and `|` a cell. A cell left empty, `||`, widens the cell
     * before it by a column; a cell `:::` widens the cell above it by a row. Two spaces or more before the text of a
     * cell align it right, after it left, on both sides center.
     *
     * @return list<Block>
     */
    private function table(Engine $engine): array
    {
        $rows = [];
        /** @var array<int, array<int, array{colspan?: int, rowspan?: int}>> $spans each row, to the spans of its cells */
        $spans = [];
        /** @var array<int, array{int, int}> $owners each column, to the row and the cell that last started in it */
        $owners = [];

        for ($line = $engine->line(); null !== $line && \in_array($line[0] ?? '', ['^', '|'], true); $line = $engine->line()) {
            $engine->advance();
            $row = [];

            foreach (Structures::cells($line, '^|') as $column => [$separator, $text]) {
                $last = array_key_last($row);
                $at = \count($rows);

                if ('' === $text && null !== $last) {
                    $spans[$at][$last]['colspan'] = min(($spans[$at][$last]['colspan'] ?? 1) + 1, Tables::MAX_COLSPAN);
                } elseif (':::' === trim($text) && isset($owners[$column])) {
                    [$above, $cell] = $owners[$column];
                    $spans[$above][$cell]['rowspan'] = min(($spans[$above][$cell]['rowspan'] ?? 1) + 1, Tables::MAX_ROWSPAN);
                } else {
                    $owners[$column] = [$at, \count($row)];
                    $row[] = ['^' === $separator, ':::' === trim($text) ? '' : $text];
                }
            }

            $rows[] = $row;
        }

        $cells = [];

        foreach ($rows as $at => $row) {
            $cells[] = array_map(fn (array $cell, int $index): array => [$cell[0], $cell[1], [...$spans[$at][$index] ?? [], ...$this->alignment($cell[1])]], $row, array_keys($row));
        }

        $table = Structures::table($engine, $cells);

        return $table instanceof Block ? [$table] : [];
    }

    /**
     * The alignment of a cell by the spaces around its text.
     *
     * @return array{align?: string}
     */
    private function alignment(string $text): array
    {
        $before = \strlen($text) - \strlen(ltrim($text, ' '));
        $after = \strlen($text) - \strlen(rtrim($text, ' '));

        return match (true) {
            '' === trim($text) => [],
            $before >= 2 && $after >= 2 => ['align' => 'center'],
            $before >= 2 => ['align' => 'right'],
            $after >= 2 => ['align' => 'left'],
            default => [],
        };
    }

    /**
     * A macro alone on its line: `~~NOTOC~~` and `~~NOCACHE~~` leave nothing, `~~TOC~~`, `~~PAGEBREAK~~`,
     * `~~COLUMNBREAK~~`, `~~SPACER:3~~`, `~~PAGENUMBER:12~~`, `~~DECORATION:12 color=#aabbcc~~` are blocks of pages.
     *
     * @return list<Block>|null
     */
    private function macro(Engine $engine, string $line, int $depth): ?array
    {
        $line = trim($line);
        $inside = str_ends_with($line, '~~') ? substr($line, 2, -2) : '';
        $name = strtolower(substr($inside, 0, strspn($inside, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')));
        $tag = Tag::parse($name.' '.ltrim(substr($inside, \strlen($name)), ': '));
        $made = match (true) {
            \in_array($name, self::SILENT, true) => [],
            !$tag instanceof Tag => null,
            default => $engine->containers()->leaf($tag, $depth),
        };

        if (null !== $made) {
            $engine->advance();
        }

        return $made;
    }

    /**
     * The lines indented by two spaces or a tab: list items, `* ` and `- `, a level for every two spaces; definitions,
     * `= Term : Definition` and `: Definition`; or code.
     *
     * @return list<Block>
     */
    private function indented(Engine $engine): array
    {
        $line = ltrim($engine->line() ?? '');

        return match (true) {
            str_starts_with($line, '* '), str_starts_with($line, '- ') => $this->listing($engine),
            str_starts_with($line, '= ') => $this->definitions($engine),
            default => $this->code($engine),
        };
    }

    /**
     * @return list<Block>
     */
    private function listing(Engine $engine): array
    {
        $lists = [];
        $entries = [];
        $kind = '';

        for ($line = $engine->line(); null !== $line && $this->item($line); $line = $engine->line()) {
            $engine->advance();
            $mark = ltrim($line)[0];
            $level = intdiv($this->columns($line), 2);

            if ([] !== $entries && 1 === $level && $mark !== $kind) {
                $lists[] = Structures::listing($engine, '-' === $kind, $entries);
                $entries = [];
            }

            $kind = [] === $entries ? $mark : $kind;
            $entries[] = [$level, substr(ltrim($line), 2)];
        }

        return [] === $entries ? $lists : [...$lists, Structures::listing($engine, '-' === $kind, $entries)];
    }

    /**
     * @return list<Block>
     */
    private function definitions(Engine $engine): array
    {
        $terms = [];
        $definitions = [];

        for ($line = $engine->line(); null !== $line && $this->columns($line) >= 2 && \in_array(substr(ltrim($line), 0, 2), ['= ', ': '], true); $line = $engine->line()) {
            $engine->advance();
            $text = substr(ltrim($line), 2);
            $split = explode(' : ', $text, 2);

            if ('=' === ltrim($line)[0]) {
                $terms[] = $split[0];
                $definitions[] = isset($split[1]) ? [$split[1]] : [];
            } else {
                $given = array_pop($definitions) ?? [];
                $given[] = $text;
                $definitions[] = $given;
            }
        }

        return [Structures::definitions($engine, $terms, $definitions)];
    }

    /**
     * Code: the lines indented by two spaces or a tab that are no items, without their first two columns.
     *
     * @return list<Block>
     */
    private function code(Engine $engine): array
    {
        $lines = [];

        for ($line = $engine->line(); null !== $line && $this->columns($line) >= 2 && '' !== trim($line) && !$this->item($line); $line = $engine->line()) {
            $engine->advance();
            $lines[] = str_starts_with($line, "\t") ? substr($line, 1) : substr($line, 2);
        }

        return [Structures::code($engine, implode("\n", $lines), '')];
    }

    private function item(string $line): bool
    {
        $text = ltrim($line);

        return $this->columns($line) >= 2 && (str_starts_with($text, '* ') || str_starts_with($text, '- '));
    }

    /**
     * The columns a line is indented by, a tab counting as two.
     */
    private function columns(string $line): int
    {
        $indent = strspn($line, " \t");

        return $indent + substr_count(substr($line, 0, $indent), "\t");
    }
}
