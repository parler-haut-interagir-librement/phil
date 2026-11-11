<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\MediaWiki;

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
 * The wikitext of MediaWiki: headings `== … ==`, `''italic''` and `'''bold'''`, links `[[Page|text]]` and
 * `[https://… text]`, files `[[File:map.png|thumb|left|200px|Caption]]`, lists `*` and `#`, terms `;` and
 * definitions or indents `:`, tables `{| … |}`, preformatted lines that start with a space, rules `----`, entities,
 * magic words `__TOC__`, and templates: `{{Footnote|…}}`, `{{TOC}}`, `{{Page break}}` and the other extensions of
 * its column. Comments `<!-- … -->` are left out.
 *
 * @internal
 */
final readonly class MediaWikiSyntax implements Syntax
{
    /** The magic words, each `true` when it asks for a table of contents. */
    private const array MAGIC = [
        '__TOC__' => true, '__FORCETOC__' => true, '__NOTOC__' => false, '__NOEDITSECTION__' => false,
        '__NEWSECTIONLINK__' => false, '__NONEWSECTIONLINK__' => false, '__NOGALLERY__' => false, '__HIDDENCAT__' => false,
        '__INDEX__' => false, '__NOINDEX__' => false, '__STATICREDIRECT__' => false,
    ];

    /** The inline templates, by the extension each one is. */
    private const array TEMPLATES = [
        'footnote' => 'footnote', 'ref' => 'footnote', 'efn' => 'footnote', 'spoiler' => 'spoiler', 'mark' => 'mark',
        'highlight' => 'mark', 'color' => 'color', 'nbsp' => 'nbsp', 'icon' => 'icon', 'hspace' => 'hspace', 'var' => 'var',
        'u' => 'u', 'underline' => 'u', 'strike' => 's', 's' => 's', 'sup' => 'sup', 'sub' => 'sub', 'code' => 'code',
        'mono' => 'tt', 'vardefine' => 'vardefine',
    ];

    /** The options of a file that only MediaWiki's own frames use. */
    private const array FRAMES = [
        'thumb', 'thumbnail', 'frame', 'framed', 'frameless', 'border', 'none', 'upright', 'baseline', 'middle', 'sub',
        'super', 'top', 'text-top', 'bottom', 'text-bottom',
    ];

    #[Override]
    public function dialect(): Dialect
    {
        return Dialect::MediaWiki;
    }

    /**
     * The source without its comments; a comment left open runs to the end.
     */
    #[Override]
    public function prepare(string $source): string
    {
        $cursor = new Cursor($source);
        $kept = '';

        while (!$cursor->isEnd()) {
            $text = $cursor->consumeThrough('<!--');
            $kept .= $text ?? $cursor->rest();

            if (null === $cursor->consumeThrough('-->')) {
                $cursor->rest();
            }
        }

        return $kept;
    }

    #[Override]
    public function triggers(): string
    {
        return "'[{&";
    }

    /**
     * @return list<Toggle|string>|string|null
     */
    #[Override]
    public function inline(Cursor $cursor, Spans $spans): array|string|null
    {
        return match ($cursor->current()) {
            "'" => $this->apostrophes($cursor, $spans),
            '[' => '[' === $cursor->peek() ? $this->internal($cursor, $spans) : $this->external($cursor, $spans),
            '{' => '{' === $cursor->peek() ? $this->template($cursor, $spans) : null,
            default => $this->entity($cursor),
        };
    }

    #[Override]
    public function block(Engine $engine, int $depth): ?array
    {
        $line = $engine->line() ?? '';

        return match ($line[0]) {
            '=' => $this->heading($engine, $line),
            '-' => $this->rule($engine, $line),
            '*', '#' => $this->listing($engine),
            ';', ':' => Structures::terms($engine),
            '{' => '|' === ($line[1] ?? '') ? $this->table($engine) : $this->leaf($engine, $line, $depth),
            ' ' => $this->preformatted($engine),
            '_' => $this->magic($engine, $line, $depth),
            default => null,
        };
    }

    /**
     * The parameters of a link or a template, split at the bars that are not inside a link or a template it holds.
     *
     * @return non-empty-list<string>
     */
    public static function parameters(string $text): array
    {
        $parameters = [''];
        $depth = 0;
        $cursor = new Cursor($text);

        while (!$cursor->isEnd()) {
            $parameters[array_key_last($parameters)] .= $cursor->consumeUntil('[]{}|');
            $pair = $cursor->advance(2);
            $nesting = match ($pair) {
                '[[', '{{' => 1,
                ']]', '}}' => -1,
                default => 0,
            };

            if (0 !== $nesting) {
                $depth = max(0, $depth + $nesting);
                $parameters[array_key_last($parameters)] .= $pair;

                continue;
            }

            $cursor->moveTo($cursor->position() - \strlen($pair));
            $character = $cursor->advance();
            $split = '|' === $character && 0 === $depth;
            $parameters = $split ? [...$parameters, ''] : $parameters;
            $parameters[array_key_last($parameters)] .= $split ? '' : $character;
        }

        return $parameters;
    }

    /**
     * A template as a tag: its name without spaces or underscores, in lower case, its parameters `key=value` its
     * attributes and the others its flags; `{{#name:first|…}}`, a parser function, takes its first parameter after
     * the colon.
     */
    public static function tag(string $inside): Tag
    {
        [$name, $parameters] = self::split($inside);
        $attributes = [];
        $flags = [];

        foreach ($parameters as $parameter) {
            $pair = explode('=', $parameter, 2);
            $key = strtolower(trim($pair[0]));

            if (isset($pair[1]) && '' !== $key && strspn($key, 'abcdefghijklmnopqrstuvwxyz-') === \strlen($key)) {
                $attributes[$key] = trim($pair[1]);
            } else {
                $flags[] = trim($parameter);
            }
        }

        return new Tag($name, $attributes, $flags);
    }

    /**
     * @return array{string, list<string>}
     */
    private static function split(string $inside): array
    {
        $parameters = self::parameters($inside);
        $name = array_shift($parameters);
        $colon = strpos($name, ':');

        if (str_starts_with($name, '#') && false !== $colon) {
            $parameters = [substr($name, $colon + 1), ...$parameters];
            $name = substr($name, 1, $colon - 1);
        }

        return [strtolower(str_replace([' ', '_'], '', $name)), $parameters];
    }

    /**
     * A run of apostrophes: two open or close italic, three bold, five both — the one open closed first —; a fourth
     * is an apostrophe before bold, and those beyond five are apostrophes too.
     *
     * @return list<Toggle|string>
     */
    private function apostrophes(Cursor $cursor, Spans $spans): array
    {
        $run = \strlen($cursor->consumeWhile("'"));

        if (1 === $run) {
            return [Spans::escape("'")];
        }

        $extra = 4 === $run ? 1 : max(0, $run - 5);
        $italic = new Toggle("''", 'i');
        $bold = new Toggle("'''", 'b');
        $toggles = match ($run - $extra) {
            2 => [$italic],
            3 => [$bold],
            default => "''" === $spans->top() ? [$italic, $bold] : [$bold, $italic],
        };

        return [Spans::escape(str_repeat("'", $extra)), ...$toggles];
    }

    /**
     * A link to a page, `[[Page]]`, `[[Page#Section|text]]`, its text followed by the letters that end a word; a
     * file, `[[File:…]]` or `[[Image:…]]`; a category, `[[Category:…]]`, which is left out.
     */
    private function internal(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $cursor->advance(2);
        $inside = $spans->balanced($cursor, '[[', ']]');

        if (null === $inside || str_contains($inside, "\n")) {
            $cursor->moveTo($start);

            return null;
        }

        $parts = self::parameters($inside);
        $target = trim($parts[0]);
        $named = explode(':', $target, 2);
        $namespace = isset($named[1]) ? strtolower(trim($named[0])) : '';

        return match ($namespace) {
            'file', 'image' => $this->image($named[1], \array_slice($parts, 1), $spans),
            'category' => '',
            default => $spans->link($spans->links->page(ltrim($target, ':')), $spans->parse((1 === \count($parts) ? ltrim($target, ':') : implode('|', \array_slice($parts, 1))).$cursor->consumeWhile('abcdefghijklmnopqrstuvwxyz'), true)),
        };
    }

    /**
     * A file shown as an image: `left` and `right` wrap the text around it, `center` centers it, `200px`, `200x100px`
     * and `x100px` size it, `alt=` is its alternative text and the last other option its caption, `class=` its classes.
     *
     * @param list<string> $options
     */
    private function image(string $name, array $options, Spans $spans): string
    {
        $tokens = [];
        $caption = '';
        $alt = '';

        foreach ($options as $option) {
            $option = trim($option);
            $lower = strtolower($option);
            $size = str_ends_with($lower, 'px') ? [...explode('x', substr($lower, 0, -2), 2), ''] : null;

            if ('left' === $lower || 'right' === $lower) {
                $tokens[] = 'left' === $lower ? 'wrapLeft' : 'wrapRight';
            } elseif ('center' === $lower) {
                $tokens[] = 'text-align:center';
            } elseif (null !== $size && array_all($size, static fn (string $value): bool => '' === $value || ctype_digit($value))) {
                $tokens = [...$tokens, ...'' === $size[0] ? [] : ['width:'.$size[0].'px'], ...'' === $size[1] ? [] : ['height:'.$size[1].'px']];
            } elseif (str_starts_with($lower, 'alt=')) {
                $alt = substr($option, 4);
            } elseif (str_starts_with($lower, 'class=')) {
                $tokens = [...$tokens, ...explode(' ', substr($option, 6))];
            } elseif (!\in_array($lower, self::FRAMES, true) && !str_starts_with($lower, 'link=') && !str_starts_with($lower, 'upright=') && !str_starts_with($lower, 'page=')) {
                $caption = $option;
            }
        }

        return $spans->image($spans->links->media($name), InlineParser::text($spans->parse($alt, true)), implode(' ', $tokens), InlineParser::text($spans->parse($caption, true)));
    }

    /**
     * A link to another site, `[https://… text]`; its address alone when it has no text.
     */
    private function external(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();

        if (!WikiLinks::isAddressAt($cursor->text, $start + 1)) {
            return null;
        }

        $cursor->advance();
        $inside = $spans->balanced($cursor, '[', ']');

        if (null === $inside || str_contains($inside, "\n")) {
            $cursor->moveTo($start);

            return null;
        }

        $space = strpos($inside, ' ');
        $address = false === $space ? $inside : substr($inside, 0, $space);
        $text = false === $space ? '' : trim(substr($inside, $space));

        return $spans->link($address, '' === $text ? Spans::escape($address) : $spans->parse($text, true));
    }

    /**
     * An inline template of an extension: `{{Footnote|…}}`, `{{Spoiler|…}}`, `{{Color|red|…}}`, `{{Icon|…}}`,
     * `{{#vardefine:name|value}}`, `{{#var:name}}`…; any other is text.
     */
    private function template(Cursor $cursor, Spans $spans): ?string
    {
        $start = $cursor->position();
        $cursor->advance(2);
        $inside = $spans->balanced($cursor, '{{', '}}');
        [$name, $parameters] = null === $inside ? ['', []] : self::split($inside);
        $extension = self::TEMPLATES[$name] ?? '';
        $first = $parameters[0] ?? '';

        $html = match ($extension) {
            'icon', 'hspace', 'var' => $spans->extension(new Tag($extension, [], $parameters), null),
            'vardefine' => $spans->extension(new Tag('var', [strtolower(trim($first)) => trim($parameters[1] ?? '')]), null),
            'color' => $spans->extension(new Tag('color', [], [trim($first)]), implode('|', \array_slice($parameters, 1))),
            default => $spans->extension(new Tag($extension), implode('|', $parameters)),
        };

        if (null === $html) {
            $cursor->moveTo($start);
        }

        return $html;
    }

    /**
     * An entity, `&nbsp;`, `&#233;`, `&#x263A;`, read as its character.
     */
    private function entity(Cursor $cursor): ?string
    {
        $start = $cursor->position();
        $cursor->advance();
        $entity = '&'.$cursor->consumeWhile('#abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789').';';
        $decoded = html_entity_decode($entity, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        if (!$cursor->consume(';') || $decoded === $entity) {
            $cursor->moveTo($start);

            return null;
        }

        return Spans::escape($decoded);
    }

    /**
     * A heading, `== Title ==`, its level the fewer of the signs that open and close it, six at most.
     *
     * @return list<Block>|null
     */
    private function heading(Engine $engine, string $line): ?array
    {
        $line = rtrim($line, " \t");
        $level = min(strspn($line, '='), strspn(strrev($line), '='), 6);
        $text = trim(substr($line, $level, \strlen($line) - 2 * $level));

        if ('' === $text) {
            return null;
        }

        $engine->advance();

        return [Structures::heading($engine, $level, $text)];
    }

    /**
     * A rule, four dashes or more; what follows them on the line is read as the next line.
     *
     * @return list<Block>|null
     */
    private function rule(Engine $engine, string $line): ?array
    {
        if (!str_starts_with($line, '----')) {
            return null;
        }

        $rest = ltrim(substr($line, strspn($line, '-')), " \t");
        '' === $rest ? $engine->advance() : $engine->replace($rest);

        return [$engine->block('delimiter', [])];
    }

    /**
     * The lines of lists, `*` and `#`, their level the number of signs; a line whose signs end with `:` continues the
     * item before it, and a sign of the other kind at the first level starts another list.
     *
     * @return list<Block>
     */
    private function listing(Engine $engine): array
    {
        $lists = [];
        $entries = [];
        $kind = '';

        for ($line = $engine->line(); null !== $line && \in_array($line[0] ?? '', ['*', '#'], true); $line = $engine->line()) {
            $engine->advance();
            $prefix = strspn($line, '*#:;');
            $level = strspn($line, '*#');
            $text = trim(substr($line, $prefix));

            $last = array_pop($entries);

            if ($level < $prefix && null !== $last) {
                $entries[] = [$last[0], $last[1].'<br>'.$text];

                continue;
            }

            if (null !== $last) {
                $entries[] = $last;
            }

            if ([] !== $entries && 1 === $level && $line[0] !== $kind) {
                $lists[] = Structures::listing($engine, '#' === $kind, $entries);
                $entries = [];
            }

            $kind = [] === $entries ? $line[0] : $kind;
            $entries[] = [$level, $text];
        }

        return [] === $entries ? $lists : [...$lists, Structures::listing($engine, '#' === $kind, $entries)];
    }

    /**
     * A table, `{|` to `|}`: `|+` its caption, `|-` starts a row, `!` heading cells and `|` cells, several on a line
     * between `!!` or `||`; of the attributes before a single `|`, `colspan`, `rowspan` and the alignment — `align` or
     * the `text-align` of `style` — are kept. A line of text continues the cell, or the caption, before it.
     *
     * @return list<Block>
     */
    private function table(Engine $engine): array
    {
        $rows = [];
        $row = [];
        $caption = null;
        $next = $engine->line();

        while (null !== $next && !str_starts_with(ltrim($next), '|}')) {
            $engine->advance();
            $trimmed = ltrim($next);
            $mark = $trimmed[0] ?? '';

            if (str_starts_with($trimmed, '|-')) {
                $rows[] = $row;
                $row = [];
            } elseif (str_starts_with($trimmed, '|+')) {
                $parts = explode('|', substr($trimmed, 2), 2);
                $caption = (null === $caption ? '' : $caption.' ').(isset($parts[1]) && str_contains($parts[0], '=') ? $parts[1] : substr($trimmed, 2));
            } elseif ('!' === $mark || '|' === $mark) {
                foreach ($this->cells(substr($trimmed, 1), '!' === $mark) as $cell) {
                    $row[] = $cell;
                }
            } elseif ([] === $row && null !== $caption) {
                $caption .= "\n".$next;
            } else {
                $last = array_pop($row);

                if (null !== $last) {
                    $row[] = [$last[0], $last[1]."\n".$next, $last[2]];
                }
            }

            $next = $engine->line();
        }

        $engine->advance();
        $table = Structures::table($engine, [...$rows, $row], $caption ?? '');

        return $table instanceof Block ? [$table] : [];
    }

    /**
     * @return list<array{bool, string, array{colspan?: int, rowspan?: int, align?: string}}>
     */
    private function cells(string $text, bool $heading): array
    {
        $cells = [];

        foreach (explode('||', $heading ? str_replace('!!', '||', $text) : $text) as $cell) {
            $parts = explode('|', $cell, 2);
            $plain = isset($parts[1]) && str_contains($parts[0], '=') && !str_contains($parts[0], '[[') && !str_contains($parts[0], '{{');
            $cells[] = [$heading, $plain ? $parts[1] : $cell, $plain ? $this->layout($parts[0]) : []];
        }

        return $cells;
    }

    /**
     * The spans and alignment of a cell from its attributes: `colspan`, `rowspan`, `align`, or the `text-align` of its
     * `style`.
     *
     * @return array{colspan?: int, rowspan?: int, align?: string}
     */
    private function layout(string $attributes): array
    {
        preg_match_all('/\b(colspan|rowspan|align|style)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/i', $attributes, $matches, \PREG_SET_ORDER);
        $values = [];

        foreach ($matches as $match) {
            $values[strtolower($match[1])] ??= ($match[2] ?? '').($match[3] ?? '').($match[4] ?? '');
        }

        preg_match('/text-align\s*:\s*([a-z]+)/i', $values['style'] ?? '', $style);

        return Tables::layout($values['colspan'] ?? null, $values['rowspan'] ?? null, $values['align'] ?? $style[1] ?? null);
    }

    /**
     * Preformatted text: the lines that start with a space, without it.
     *
     * @return list<Block>
     */
    private function preformatted(Engine $engine): array
    {
        $lines = [];

        for ($line = $engine->line(); null !== $line && str_starts_with($line, ' ') && '' !== trim($line); $line = $engine->line()) {
            $engine->advance();
            $lines[] = substr($line, 1);
        }

        return [Structures::code($engine, implode("\n", $lines), '')];
    }

    /**
     * A magic word alone on its line: `__TOC__` and `__FORCETOC__` place a table of contents, the others leave
     * nothing.
     *
     * @return list<Block>|null
     */
    private function magic(Engine $engine, string $line, int $depth): ?array
    {
        $word = trim($line);

        if (!isset(self::MAGIC[$word])) {
            return null;
        }

        $engine->advance();

        return self::MAGIC[$word] ? $engine->containers()->leaf(new Tag('toc'), $depth) : [];
    }

    /**
     * A template of a block of pages alone on its line: `{{TOC}}`, `{{Page break|frontCover}}`, `{{Column break}}`,
     * `{{Spacer|3}}`, `{{Page number|12}}`, `{{Decoration|12|color=#aabbcc}}`.
     *
     * @return list<Block>|null
     */
    private function leaf(Engine $engine, string $line, int $depth): ?array
    {
        $line = trim($line);
        $made = str_starts_with($line, '{{') && str_ends_with($line, '}}') ? $engine->containers()->leaf(self::tag(substr($line, 2, -2)), $depth) : null;

        if (null !== $made) {
            $engine->advance();
        }

        return $made;
    }
}
