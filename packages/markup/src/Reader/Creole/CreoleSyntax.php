<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Creole;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Wiki\Dialect;
use Phil\Markup\Reader\Wiki\Engine;
use Phil\Markup\Reader\Wiki\Spans;
use Phil\Markup\Reader\Wiki\Structures;
use Phil\Markup\Reader\Wiki\Syntax;
use Phil\Markup\Reader\Wiki\Toggle;
use Phil\Markup\Reader\Wiki\WikiLinks;
use Phil\Ujo\Block;

/**
 * Creole 1.0 and its additions: headings `= … =`, `**bold**`, `//italic//`, links `[[page|text]]`, images
 * `{{image.png|text}}`, `{{{nowiki}}}` inline and as a block, line breaks `\\`, the escape `~`, lists `*` and `#`,
 * tables of `|=` headings and `|` cells, rules `----`; `##monospace##`, `^^superscript^^`, `,,subscript,,`,
 * `__underline__`, terms `;`, definitions and indents `:`, quotes `>`; and the extensions in its own style,
 * placeholders: `<<footnote>>…<</footnote>>`, `<<toc>>`, `<<note title="…">>…<</note>>`. A placeholder `<<<…>>>`, for
 * the wiki that wrote it, is left out.
 *
 * @internal
 */
final readonly class CreoleSyntax implements Syntax
{
    #[Override]
    public function dialect(): Dialect
    {
        return Dialect::Creole;
    }

    #[Override]
    public function prepare(string $source): string
    {
        return $source;
    }

    #[Override]
    public function triggers(): string
    {
        return '*/#^,_[{\\~';
    }

    #[Override]
    public function inline(Cursor $cursor, Spans $spans): Toggle|string|null
    {
        return match ($cursor->current()) {
            '*' => $this->toggle($cursor, '**', 'b'),
            '/' => $this->toggle($cursor, '//', 'i'),
            '#' => $this->toggle($cursor, '##', 'code'),
            '^' => $this->toggle($cursor, '^^', 'sup'),
            ',' => $this->toggle($cursor, ',,', 'sub'),
            '_' => $this->toggle($cursor, '__', 'u'),
            '[' => $this->link($cursor, $spans),
            '{' => $this->nowiki($cursor, $spans) ?? $this->image($cursor, $spans),
            '\\' => $cursor->consume('\\\\') ? '<br>' : null,
            '~' => $this->escape($cursor),
            default => $this->placeholder($cursor, $spans),
        };
    }

    #[Override]
    public function block(Engine $engine, int $depth): ?array
    {
        $line = $engine->line() ?? '';
        $trimmed = trim($line);

        return match (true) {
            '=' === $trimmed[0] => $this->heading($engine, $trimmed),
            \strlen($trimmed) >= 4 && strspn($trimmed, '-') === \strlen($trimmed) => $this->rule($engine),
            '{{{' === $trimmed => $this->preformatted($engine),
            '|' === $trimmed[0] => $this->table($engine),
            '*' === $trimmed[0] || '#' === $trimmed[0] => $this->listing($engine),
            ';' === $line[0] || ':' === $line[0] => Structures::terms($engine),
            '>' === $line[0] => Structures::quote($engine),
            default => null,
        };
    }

    private function toggle(Cursor $cursor, string $marks, string $tag): ?Toggle
    {
        return $cursor->consume($marks) ? new Toggle($marks, $tag) : null;
    }

    /**
     * A link, `[[page]]`, `[[page#section|text]]`, `[[https://…|text]]`, its text an image or text.
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
        $href = WikiLinks::isAddress($target) ? $target : $spans->links->page($target);

        return $spans->link($href, $spans->parse($parts[1] ?? $target, true));
    }

    /**
     * Text kept as written and shown as code, `{{{text}}}`: the braces that close it are the last three of their run.
     */
    private function nowiki(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $content = $cursor->consume('{{{') ? $spans->through($cursor, '}}}') : null;

        if (null === $content) {
            $cursor->moveTo($start);

            return null;
        }

        $more = $cursor->consumeWhile('}');

        return '<code>'.Spans::escape($content.$more).'</code>';
    }

    /**
     * An image, `{{image.png|text}}`, its text its alternative text: Creole gives an image no caption.
     */
    private function image(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $inside = $cursor->consume('{{') ? $spans->balanced($cursor, '{{', '}}') : null;

        if (null === $inside || str_contains($inside, "\n")) {
            $cursor->moveTo($start);

            return null;
        }

        $parts = explode('|', $inside, 2);

        return $spans->image($spans->links->media($parts[0]), trim($parts[1] ?? ''), '', '');
    }

    /**
     * The escape, `~`: the character after it is text; before a space or at the end, it is itself.
     */
    private function escape(Cursor $cursor): ?string
    {
        $next = mb_substr(substr($cursor->text, $cursor->position() + 1, 4), 0, 1);

        if ('' === trim($next)) {
            return null;
        }

        $cursor->advance(1 + \strlen($next));

        return Spans::escape($next);
    }

    /**
     * A placeholder, `<<<…>>>`, left out.
     */
    private function placeholder(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $content = $cursor->consume('<<<') ? $spans->through($cursor, '>>>') : null;

        if (null === $content) {
            $cursor->moveTo($start);
        }

        return null === $content ? null : '';
    }

    /**
     * A heading, `== Title ==`, its level the signs that open it, six at most; the signs that close it are optional.
     *
     * @return list<Block>|null
     */
    private function heading(Engine $engine, string $line): ?array
    {
        $signs = strspn($line, '=');
        $text = rtrim(substr($line, $signs), '= ');

        if ('' === $text) {
            return null;
        }

        $engine->advance();

        return [Structures::heading($engine, min($signs, 6), $text)];
    }

    /**
     * @return list<Block>
     */
    private function rule(Engine $engine): array
    {
        $engine->advance();

        return [$engine->block('delimiter', [])];
    }

    /**
     * Preformatted text, between a line `{{{` and a line `}}}`; a line ` }}}`, indented, is text, its space left out.
     *
     * @return list<Block>
     */
    private function preformatted(Engine $engine): array
    {
        $engine->advance();
        $lines = [];
        $line = $engine->line();

        while (null !== $line && '}}}' !== rtrim($line)) {
            $engine->advance();
            $lines[] = '}}}' === trim($line) ? substr($line, 1) : $line;
            $line = $engine->line();
        }

        $engine->advance();

        return [Structures::code($engine, implode("\n", $lines), '')];
    }

    /**
     * A table: each line a row of cells opened by `|`, a cell whose text starts with `=` a heading.
     *
     * @return list<Block>
     */
    private function table(Engine $engine): array
    {
        $rows = [];

        for ($line = $engine->line(); null !== $line && str_starts_with(ltrim($line), '|'); $line = $engine->line()) {
            $engine->advance();
            $rows[] = array_map(static fn (array $cell): array => str_starts_with($cell[1], '=') ? [true, substr($cell[1], 1)] : [false, $cell[1]], Structures::cells(ltrim($line), '|'));
        }

        $table = Structures::table($engine, $rows);

        return $table instanceof Block ? [$table] : [];
    }

    /**
     * The lines of lists, `*` and `#`, their level the number of signs: a list starts with a single sign, so that a
     * paragraph may start with bold, and a sign of the other kind at the first level starts another list.
     *
     * @return list<Block>|null
     */
    private function listing(Engine $engine): ?array
    {
        $first = ltrim($engine->line() ?? '');

        if (1 !== strspn($first, '*#')) {
            return null;
        }

        $lists = [];
        $entries = [];
        $kind = '';

        for ($line = $engine->line(); null !== $line && \in_array(ltrim($line)[0] ?? '', ['*', '#'], true); $line = $engine->line()) {
            $engine->advance();
            $text = ltrim($line);
            $level = strspn($text, '*#');

            if ([] !== $entries && 1 === $level && $text[0] !== $kind) {
                $lists[] = Structures::listing($engine, '#' === $kind, $entries);
                $entries = [];
            }

            $kind = [] === $entries ? $text[0] : $kind;
            $entries[] = [$level, substr($text, $level)];
        }

        return [] === $entries ? $lists : [...$lists, Structures::listing($engine, '#' === $kind, $entries)];
    }
}
