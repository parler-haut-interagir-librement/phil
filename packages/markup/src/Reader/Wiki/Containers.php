<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Reader\BBCode\Builder;
use Phil\Markup\Reader\BBCode\Pages;
use Phil\Markup\Reader\Markdown\Attributes;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Curly;
use Phil\Markup\Reader\Markdown\FrontMatter;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\Layout;
use Phil\Ujo\Block;

/**
 * The extension tags of blocks every wiki shares, as the BBCode reader makes them: containers — notes, read-aloud
 * text, warnings, alerts, quotes, spoilers, alignment, columns, stat blocks, credits, class tables, any class of the
 * look, running footers, watermarks, banners —, blocks of pages that hold nothing — a table of contents, breaks,
 * spacers, page numbers, decorations —, and tags whose content is kept as written — code, preformatted text, raw HTML,
 * metadata and style. Each takes the attributes `id`, `class`, `align`, `width`, `height` and `wrap`.
 *
 * Before the blocks are read, the tags of containers are set on lines of their own, so that `<note>a</note>` is read
 * as a note holding a paragraph.
 *
 * @internal
 */
final readonly class Containers
{
    private const array CONTAINERS = [
        'note', 'descriptive', 'warning', 'alert', 'wide', 'block', 'div', 'wrap', 'columns', 'statblock', 'monster', 'credit',
        'artist', 'classtable', 'spoiler', 'toggle', 'hidden', 'quote', 'blockquote', 'center', 'left', 'right', 'justify',
        'align', 'footer', 'watermark', 'banner',
    ];

    private const array SEPARATORS = ['column', 'newcolumn'];

    private const array LEAVES = ['toc', 'pagebreak', 'columnbreak', 'spacer', 'pagenumber', 'decoration', 'references'];

    private const array RAW = ['code', 'nowiki', 'html', 'pre', 'syntaxhighlight', 'source', 'file', 'metadata', 'style'];

    /** The tags that are inline when they have no title or language and close on the line they open. */
    private const array AMBIGUOUS = ['spoiler', 'code', 'nowiki', 'html'];

    private const array ALERTS = ['info', 'success', 'danger', 'primary', 'secondary', 'light', 'dark'];

    /** The kind of `Curly` each container is. */
    private const array KINDS = [
        'note' => 'note', 'descriptive' => 'descriptive', 'warning' => 'warning', 'credit' => 'artist', 'artist' => 'artist',
        'wide' => 'wide', 'monster' => 'monster', 'classtable' => 'classTable', 'statblock' => 'statblock',
    ];

    /** The kinds of the note plugin of DokuWiki, `<note warning>`, as the boxes they are. */
    private const array NOTES = ['warning' => 'warning', 'important' => 'danger', 'tip' => 'success'];

    private Dialect $dialect;

    private Pages $pages;

    public function __construct(
        private Engine $engine,
    ) {
        $this->dialect = $engine->spans->dialect;
        $this->pages = new Pages(new Builder($engine->context(), $engine->inline, []), $engine->context(), $engine->inline);
    }

    /**
     * The tag of blocks a line starts with, and the rest of the line; `null` when it starts with none.
     *
     * @return array{Tag, string}|null
     */
    public function opening(string $line): ?array
    {
        $opener = $this->dialect->opener();

        if (!str_starts_with($line, $opener)) {
            return null;
        }

        $end = strpos($line, $this->dialect->closer(), \strlen($opener));

        if (false === $end) {
            return null;
        }

        $tag = Tag::parse(substr($line, \strlen($opener), $end - \strlen($opener)));

        if (!$tag instanceof Tag) {
            return null;
        }

        $rest = substr($line, $end + \strlen($this->dialect->closer()));

        return $this->isBlock($tag, $rest) ? [$tag, $rest] : null;
    }

    /**
     * The lines of a source, each tag of a container at the start of a line: the content after a tag starts the next
     * line, and a closing tag of a container open — or a column of columns open — ends the line before it. The
     * content of a tag kept as written is left as it is. How many containers of each name are open is counted, so
     * that a tag is tracked in constant time, amortized.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public function split(array $lines): array
    {
        $split = [];
        $open = [];
        $counts = [];
        $raw = null;

        foreach ($lines as $line) {
            if (null !== $raw) {
                $split[] = $line;
                $raw = false === stripos($line, $raw) ? $raw : null;

                continue;
            }

            while (true) {
                $found = $this->opening($line);

                if (null !== $found && \in_array($found[0]->name, self::RAW, true) && !$found[0]->closing) {
                    $split[] = $line;
                    $close = $this->dialect->closing($found[0]->name);
                    $raw = false === stripos($found[1], $close) ? $close : null;

                    break;
                }

                if (null !== $found && !\in_array($found[0]->name, self::LEAVES, true)) {
                    $split[] = substr($line, 0, \strlen($line) - \strlen($found[1]));
                    $name = $found[0]->name;

                    if (!$found[0]->closing && \in_array($name, self::CONTAINERS, true)) {
                        $open[] = $name;
                        $counts[$name] = ($counts[$name] ?? 0) + 1;
                    }

                    while ($found[0]->closing && isset($counts[$name])) {
                        $closed = array_pop($open) ?? $name;
                        if (isset($counts[$closed]) && 0 === --$counts[$closed]) {
                            unset($counts[$closed]);
                        }

                        if ($closed === $name) {
                            break;
                        }
                    }

                    $line = ltrim($found[1], " \t");

                    continue;
                }

                $cut = $this->cut($line, $counts);

                if (null === $cut) {
                    $split[] = $line;

                    break;
                }

                $split[] = substr($line, 0, $cut);
                $line = substr($line, $cut);
            }
        }

        return $split;
    }

    /**
     * The blocks of a tag at the start of the current line, its lines consumed; a closing tag or a column that ends
     * nothing open is left out.
     *
     * @param list<string> $stops
     *
     * @return list<Block>
     */
    public function block(Tag $tag, string $rest, int $depth, array $stops): array
    {
        if ($tag->closing || \in_array($tag->name, self::SEPARATORS, true)) {
            $this->engine->advance();

            return [];
        }

        if (\in_array($tag->name, self::RAW, true)) {
            return $this->raw($tag, $rest);
        }

        if (\in_array($tag->name, self::LEAVES, true)) {
            $this->engine->replace(ltrim($rest, " \t"));

            return $this->leaf($tag, $depth) ?? [];
        }

        return $this->container($tag, $depth, $stops);
    }

    /**
     * The block of a page a tag that holds nothing stands for; `null` for any other tag.
     *
     * @return list<Block>|null
     */
    public function leaf(Tag $tag, int $depth): ?array
    {
        return match ($tag->name) {
            'toc' => $this->pages->block($tag->node($tag->attribute('title')), $depth),
            'spacer', 'pagenumber', 'decoration' => $this->pages->block($tag->node($tag->first()), $depth),
            'pagebreak', 'columnbreak' => $this->pages->block($tag->node(null), $depth),
            default => null,
        };
    }

    private function isBlock(Tag $tag, string $rest): bool
    {
        if ($tag->closing) {
            return \in_array($tag->name, [...self::CONTAINERS, 'column'], true);
        }

        if (\in_array($tag->name, self::AMBIGUOUS, true)) {
            return '' !== $tag->attribute('title') || '' !== $tag->attribute('lang') || ('code' === $tag->name && null !== $tag->first()) || false === stripos($rest, $this->dialect->closing($tag->name));
        }

        return \in_array($tag->name, [...self::CONTAINERS, ...self::SEPARATORS, ...self::LEAVES, ...self::RAW], true);
    }

    /**
     * Where a line is cut before a closing tag of a container open, or before a column of columns open; `null` when
     * it holds none. Only the names are looked at before a tag is read whole, and a line without the closing
     * delimiter holds no tag after it, so that a line is read in linear time.
     *
     * @param array<string, int> $open how many containers of each name are open, the names of none left out
     */
    private function cut(string $line, array $open): ?int
    {
        $opener = $this->dialect->opener();

        for ($at = strpos($line, $opener); false !== $at; $at = strpos($line, $opener, $at + 1)) {
            $from = $at + \strlen($opener);
            $closing = '/' === ($line[$from] ?? '');
            $name = strtolower(substr($line, $from + ($closing ? 1 : 0), strspn($line, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $from + ($closing ? 1 : 0))));
            $wanted = ($closing && isset($open[$name])) || (\in_array($name, self::SEPARATORS, true) && isset($open['columns']));
            $end = $wanted ? strpos($line, $this->dialect->closer(), $from) : $from;

            if (false === $end) {
                return null;
            }

            if ($wanted && Tag::parse(substr($line, $from, $end - $from)) instanceof Tag) {
                return $at;
            }
        }

        return null;
    }

    /**
     * A tag whose content is kept as written, up to its closing tag — on the same line or a line after it —, or to
     * the end: code, preformatted text, raw HTML, metadata or style.
     *
     * @return list<Block>
     */
    private function raw(Tag $tag, string $rest): array
    {
        $close = $this->dialect->closing($tag->name);
        $content = [];
        $line = $rest;

        while (null !== $line) {
            $at = stripos($line, $close);

            if (false !== $at) {
                $content[] = substr($line, 0, $at);
                $after = substr($line, $at + \strlen($close));
                $this->engine->replace(ltrim($after, " \t"));

                break;
            }

            $content[] = $line;
            $this->engine->advance();
            $line = $this->engine->line();
        }

        $text = trim(implode("\n", $content), "\n");

        return match ($tag->name) {
            'html' => $this->html($text),
            'metadata' => $this->metadata(FrontMatter::yaml($text) ?? []),
            'style' => $this->metadata(['style' => $text]),
            'code', 'file' => [Structures::code($this->engine, $text, '' === $tag->attribute('lang') ? $tag->first() ?? '' : $tag->attribute('lang'))],
            'syntaxhighlight', 'source' => [Structures::code($this->engine, $text, $tag->attribute('lang'))],
            default => [Structures::code($this->engine, $text, '')],
        };
    }

    /**
     * Raw HTML: a `raw` block when the reading keeps it, its text escaped otherwise.
     *
     * @return list<Block>
     */
    private function html(string $html): array
    {
        return match (true) {
            '' === trim($html) => [],
            $this->engine->context()->rawHtml => [$this->engine->block('raw', ['html' => trim($html)])],
            default => [$this->engine->block('paragraph', ['text' => Inline::html(str_replace("\n", '<br>', Spans::escape(trim($html))))])],
        };
    }

    /**
     * @param array<array-key, mixed> $metadata
     *
     * @return list<Block>
     */
    private function metadata(array $metadata): array
    {
        $this->engine->metadata = [...$this->engine->metadata, ...$metadata];

        return [];
    }

    /**
     * A container: the blocks up to its closing tag, read one level deeper, made the block of its kind, its
     * attributes applied. The tag of a container nested deeper than UJO allows is left out, and its blocks are read
     * as blocks of the container around it, which its closing tag closes.
     *
     * @param list<string> $stops
     *
     * @return list<Block>
     */
    private function container(Tag $tag, int $depth, array $stops): array
    {
        $inner = Pages::deeper($tag->node(null), $depth);
        $this->engine->advance();

        if (null === $inner) {
            return [];
        }

        $groups = 'columns' === $tag->name ? $this->groups($inner, $stops) : [$this->engine->parse($inner, [...$stops, '/'.$tag->name])];
        $found = $this->opening($this->engine->line() ?? '');

        if (null !== $found && '/'.$tag->name === $found[0]->key()) {
            $this->engine->advance();
        }

        $made = 'columns' === $tag->name ? $this->columns($tag, $groups) : $this->made($tag, $groups[0]);
        $classes = !\in_array($tag->name, ['block', 'div', 'wrap'], true);

        return [] === $made ? [] : [Layout::apply($made[0], $this->attributes($tag, $classes), $this->engine->context()), ...\array_slice($made, 1)];
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private function made(Tag $tag, array $blocks): array
    {
        $title = $tag->attribute('title');
        $titled = '' === $title ? $blocks : [$this->engine->block('header', ['text' => Inline::html(Spans::escape($title))]), ...$blocks];

        return match ($tag->name) {
            'note' => $this->curly($tag, [self::NOTES[$tag->first() ?? ''] ?? 'note', ...$tag->flags], $titled),
            'descriptive', 'warning', 'credit', 'artist' => $this->curly($tag, [self::KINDS[$tag->name], ...$tag->flags], $titled),
            'alert' => $this->curly($tag, [array_find(self::ALERTS, static fn (string $type): bool => $type === $tag->first()) ?? 'info'], $titled),
            'block', 'div', 'wrap' => $this->curly($tag, [...Builder::words($tag->attribute('class')), ...$tag->flags], $blocks),
            'wide', 'monster', 'classtable', 'statblock' => $this->curly($tag, [self::KINDS[$tag->name], ...$tag->flags], $blocks),
            'spoiler', 'toggle', 'hidden' => [$this->engine->block('toggle', ['text' => Inline::html(Spans::escape($title)), 'blocks' => $blocks])],
            'quote', 'blockquote' => [$this->engine->block('quote', ['text' => BlockParser::join($blocks, '<br><br>'), 'caption' => Inline::html(Spans::escape($tag->attribute('author')))])],
            'center', 'left', 'right', 'justify', 'align' => $this->align($tag, $blocks),
            'footer' => [$this->engine->block('runningFooter', ['text' => BlockParser::join($blocks, '<br>')])],
            default => [$this->engine->block($tag->name, ['text' => $this->engine->inline->cut(BlockParser::join($blocks, '<br>'), 100)])],
        };
    }

    /**
     * A box of `Curly`, its kind and classes given; a stat block takes its name and subtitle from the attributes.
     *
     * @param list<string> $classes
     * @param list<Block>  $blocks
     *
     * @return list<Block>
     */
    private function curly(Tag $tag, array $classes, array $blocks): array
    {
        $made = Curly::block(new Attributes('', $classes), $blocks, $this->engine->context(), $this->engine->inline);
        $name = mb_substr($tag->attribute('name'), 0, 200);
        $subtitle = $tag->attribute('subtitle');
        $given = [...'' === $name ? [] : ['name' => $name], ...'' === $subtitle ? [] : ['subtitle' => Inline::html(Spans::escape($subtitle))]];

        if ([] === $given || 'statblock' !== ($made[0]->type ?? '')) {
            return $made;
        }

        return [$made[0]->withData([...$made[0]->data, ...$given])];
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private function align(Tag $tag, array $blocks): array
    {
        $alignment = 'align' === $tag->name ? strtolower($tag->first() ?? '') : $tag->name;

        if (!\in_array($alignment, ['left', 'center', 'right', 'justify'], true)) {
            return $blocks;
        }

        return array_map(static fn (Block $block): Block => $block->withTunes([...$block->tunes, 'alignment' => ['alignment' => $alignment]]), $blocks);
    }

    /**
     * The blocks of columns, a group for each column: a column starts at `<column>` — or `<newcolumn>` — and the
     * blocks before the first make a group of their own.
     *
     * @param list<string> $stops
     *
     * @return non-empty-list<list<Block>>
     */
    private function groups(int $depth, array $stops): array
    {
        $groups = [];
        $stops = [...$stops, '/columns', 'column', 'newcolumn', '/column'];

        do {
            $groups[] = $this->engine->parse($depth, $stops);
            $found = $this->opening($this->engine->line() ?? '');
            $more = null !== $found && \in_array($found[0]->key(), ['column', 'newcolumn', '/column'], true);

            if ($more) {
                $this->engine->advance();
            }
        } while ($more);

        return $groups;
    }

    /**
     * Columns side by side, six at most, the blocks of the others joined to the sixth; or, written without a column,
     * blocks flowing in columns, `<columns 3>`.
     *
     * @param non-empty-list<list<Block>> $groups
     *
     * @return list<Block>
     */
    private function columns(Tag $tag, array $groups): array
    {
        if (1 === \count($groups)) {
            $count = filter_var($tag->first(), \FILTER_VALIDATE_INT);

            return [$this->engine->block('columnFlow', ['count' => false === $count ? 2 : max(2, min(6, $count)), 'blocks' => $groups[0]])];
        }

        $groups = [] === $groups[0] ? \array_slice($groups, 1) : $groups;
        $columns = array_values(array_filter([...\array_slice($groups, 0, 5), array_merge(...\array_slice($groups, 5))], static fn (array $blocks): bool => [] !== $blocks));

        return [] === $columns ? [] : [$this->engine->block('columns', ['cols' => array_map(static fn (array $blocks): array => ['blocks' => $blocks], $columns)])];
    }

    private function attributes(Tag $tag, bool $classes): Attributes
    {
        $wrap = ['left' => ['wrapLeft'], 'right' => ['wrapRight']][$tag->attribute('wrap')] ?? [];
        $styles = ['text-align' => $tag->attribute('align'), 'width' => $tag->attribute('width'), 'height' => $tag->attribute('height')];

        return new Attributes($tag->attribute('id'), [...$classes ? Builder::words($tag->attribute('class')) : [], ...$wrap], $styles);
    }
}
