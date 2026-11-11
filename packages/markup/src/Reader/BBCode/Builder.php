<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\BBCode;

use Phil\Markup\Adapter\Autolink;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Attributes;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Layout;
use Phil\Markup\Reader\Markdown\Rule\RubyRule;
use Phil\Markup\Reader\Tables;
use Phil\Ujo\Block;
use Phil\Ujo\Html\AttributeValue;
use Phil\Ujo\Html\SafeUrl;

/**
 * The second pass of a BBCode reading: the tree of tags made into UJO blocks, whose inline content is HTML already.
 * Text between blocks gathers into paragraphs; a heading, a quote, a list item, a cell, a term, a definition and a
 * footnote fold the blocks they hold into their inline content; an inline tag that holds a block is left out, its
 * content kept. Containers of blocks nest as deep as UJO allows, and those deeper are left out, their blocks kept.
 *
 * @internal
 */
final readonly class Builder
{
    /** The depth of the blocks folded into inline content: deeper than any container may nest. */
    public const int FLAT = 8;

    private const array INLINE = ['b' => 'b', 'i' => 'i', 'u' => 'u', 's' => 's', 'sup' => 'sup', 'sub' => 'sub', 'mark' => 'mark', 'highlight' => 'mark'];

    private const array COUNTERS = ['a' => 'lower-alpha', 'A' => 'upper-alpha', 'i' => 'lower-roman', 'I' => 'upper-roman'];

    private const array ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /** The cells a row keeps; the content of those after the last is joined to it. */
    private const int MAX_CELLS = 64;

    private Pages $pages;

    /**
     * @param array<string, string> $variables
     */
    public function __construct(
        private Context $context,
        InlineParser $inline,
        private array $variables,
    ) {
        $this->pages = new Pages($this, $context, $inline);
    }

    /**
     * The blocks a tag holds, read at a depth of nesting.
     *
     * @return list<Block>
     */
    public function blocks(Node $node, int $depth): array
    {
        $flow = new Flow($this->context);
        $this->walk($node, $depth, $flow);

        return $flow->blocks();
    }

    /**
     * The blocks a tag holds folded into inline content, a separator between two.
     */
    public function flat(Node $node, string $separator = '<br>'): Inline
    {
        return BlockParser::join($this->blocks($node, self::FLAT), $separator);
    }

    public static function escape(string $text): string
    {
        return Autolink::escape($text);
    }

    private function walk(Node $node, int $depth, Flow $flow): void
    {
        foreach ($node->children as $child) {
            if (\is_string($child)) {
                $this->text($child, $flow);
            } elseif ($child->isBlock()) {
                $flow->block(...$this->block($child, $depth));
            } elseif ($child->holdsBlock && 'footnote' !== $child->name) {
                $this->walk($child, $depth, $flow);
            } else {
                $flow->inline($this->inline($child, false));
            }
        }
    }

    /**
     * Text at the level of blocks: a blank line between two lines ends the paragraph.
     */
    private function text(string $text, Flow $flow): void
    {
        $lines = explode("\n", $text);
        $last = \count($lines) - 1;

        foreach ($lines as $index => $line) {
            if ($index > 0 && $index < $last && '' === trim($line)) {
                $flow->flush();

                continue;
            }

            $flow->inline(($index > 0 ? "\n" : '').Autolink::html($line));
        }
    }

    /**
     * @return list<Block>
     */
    private function block(Node $node, int $depth): array
    {
        $made = match ($node->name) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => [$this->context->block('header', ['text' => $this->flat($node), 'level' => (int) $node->name[1]])],
            'quote' => [$this->context->block('quote', ['text' => $this->flat($node, '<br><br>'), 'caption' => Inline::html(self::escape($node->option ?? ''))])],
            'code' => [$this->code($node)],
            'html' => $this->html($node),
            'hr' => [$this->context->block('delimiter', [])],
            'list' => $this->listing($node),
            'table' => $this->table($node),
            'dl' => $this->definitions($node),
            'center', 'left', 'right', 'justify', 'align' => $this->align($node, $depth),
            default => $this->pages->block($node, $depth) ?? $this->blocks($node, $depth),
        };

        if ([] === $made) {
            return [];
        }

        return [Layout::apply($made[0], $this->attributes($node), $this->context, null !== Pages::deeper($node, $depth)), ...\array_slice($made, 1)];
    }

    /**
     * The attributes of a block tag as the Markdown reader applies them: `id`, `class`, `align`, `width`, `height`
     * and `wrap`.
     */
    private function attributes(Node $node): Attributes
    {
        $wrap = ['left' => ['wrapLeft'], 'right' => ['wrapRight']][$node->attribute('wrap')] ?? [];
        $styles = ['text-align' => $node->attribute('align'), 'width' => $node->attribute('width'), 'height' => $node->attribute('height')];

        return new Attributes($node->attribute('id'), [...self::words($node->attribute('class')), ...$wrap], $styles);
    }

    /**
     * The words of a list written with spaces or commas.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $cursor = new Cursor($text);
        $words = [];

        for ($cursor->consumeWhile(' ,'); !$cursor->isEnd(); $cursor->consumeWhile(' ,')) {
            $words[] = $cursor->consumeUntil(' ,');
        }

        return $words;
    }

    private function code(Node $node): Block
    {
        $language = $node->option ?? '';
        $valid = '' !== $language && \strlen($language) <= 32 && strspn($language, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_+#.-') === \strlen($language);

        return $this->context->block('code', ['code' => trim($node->text(), "\n"), ...$valid ? ['language' => $language] : []]);
    }

    /**
     * Raw HTML: a `raw` block when the reading keeps it, its text escaped otherwise.
     *
     * @return list<Block>
     */
    private function html(Node $node): array
    {
        $html = trim($node->text());

        return match (true) {
            '' === $html => [],
            $this->context->rawHtml => [$this->context->block('raw', ['html' => $html])],
            default => [$this->context->block('paragraph', ['text' => Inline::html(str_replace("\n", '<br>', self::escape($html)))])],
        };
    }

    /**
     * @return list<Block>
     */
    private function align(Node $node, int $depth): array
    {
        $alignment = 'align' === $node->name ? strtolower($node->option ?? '') : $node->name;
        $blocks = $this->blocks($node, $depth);

        if (!\in_array($alignment, self::ALIGNMENTS, true)) {
            return $blocks;
        }

        return array_map(static fn (Block $block): Block => $block->withTunes([...$block->tunes, 'alignment' => ['alignment' => $alignment]]), $blocks);
    }

    /**
     * A list, `[list]`, `[list=1]` — or a number to start at, `a`, `A`, `i`, `I` —, `[list=check]`, its items `[*]`,
     * checked by `[*=x]` in a checklist. Text and tags before the first item make an item.
     *
     * @return list<Block>
     */
    private function listing(Node $node): array
    {
        $option = $node->option ?? '';
        $start = filter_var($option, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $checklist = 'check' === strtolower($option);
        $counter = self::COUNTERS[$option] ?? null;
        $items = [];

        foreach ($this->groups($node, '*') as $item) {
            $items[] = $this->item($item, $checklist);
        }

        if ([] === $items) {
            return [];
        }

        $style = match (true) {
            $checklist => 'checklist',
            false !== $start || null !== $counter => 'ordered',
            default => 'unordered',
        };
        $meta = match (true) {
            null !== $counter => ['counterType' => $counter],
            false !== $start && 1 !== $start => ['start' => $start],
            default => [],
        };

        return [$this->context->block('list', ['style' => $style, 'meta' => $meta, 'items' => $items])];
    }

    /**
     * @return array{content: Inline, meta: array{checked?: bool}, items: list<mixed>}
     */
    private function item(Node $node, bool $checklist): array
    {
        $content = [];
        $children = [];

        foreach ($this->blocks($node, self::FLAT) as $block) {
            if ('list' === $block->type) {
                $children = [...$children, ...BlockParser::items($block)];
            } else {
                $content[] = $block;
            }
        }

        return ['content' => BlockParser::join($content, '<br>'), 'meta' => $checklist ? ['checked' => 'x' === strtolower($node->option ?? '')] : [], 'items' => $children];
    }

    /**
     * The children of a structure, each tag of a name its own group, and the text and other tags between them
     * gathered into a group of that name; a group of blank text is left out.
     *
     * @return list<Node>
     */
    public function groups(Node $node, string ...$names): array
    {
        $groups = [];
        $stray = null;

        foreach ($node->children as $child) {
            if ($child instanceof Node && \in_array($child->name, $names, true)) {
                $groups[] = $child;
                $stray = null;
            } elseif ($stray instanceof Node) {
                $stray->add($child);
            } elseif ($child instanceof Node || '' !== trim($child)) {
                $stray = new Node($names[0]);
                $stray->add($child);
                $groups[] = $stray;
            }
        }

        return $groups;
    }

    /**
     * A table of rows `[tr]` and cells `[td]` or `[th]`, its rows made as long as the longest; a first row of `[th]`
     * only is its headings. A cell spans columns and rows by its `colspan` and `rowspan`, and aligns by its `align`;
     * a `[th]` outside the heading row is a header cell.
     *
     * @return list<Block>
     */
    private function table(Node $node): array
    {
        $rows = [];
        $headings = [];

        foreach ($this->groups($node, 'tr') as $row) {
            $cells = [];
            $heading = true;

            foreach ($this->groups($row, 'td', 'th') as $cell) {
                $header = 'th' === $cell->name && '' !== $cell->written;
                $heading = $heading && $header;
                $content = ['text' => $this->flat($cell), 'header' => $header, ...Tables::layout($cell->attribute('colspan'), $cell->attribute('rowspan'), $cell->attribute('align'))];
                $cells = \count($cells) < self::MAX_CELLS ? [...$cells, $content] : [...\array_slice($cells, 0, -1), [...$cells[self::MAX_CELLS - 1], 'text' => Inline::join([$cells[self::MAX_CELLS - 1]['text'], $content['text']], ' ')]];
            }

            $rows[] = $cells;
            $headings[] = $heading && [] !== $cells;
        }

        if ([] === $rows) {
            return [];
        }

        return [$this->context->block('table', Tables::data($rows, $headings[0]))];
    }

    /**
     * A list of definitions, `[dl]`, of terms `[dt]` each followed by their definitions `[dd]`.
     *
     * @return list<Block>
     */
    private function definitions(Node $node): array
    {
        $terms = [];
        $definitions = [];

        foreach ($this->groups($node, 'dt', 'dd') as $group) {
            $content = $this->flat($group);

            if (('dt' === $group->name && '' !== $group->written) || [] === $terms) {
                $terms[] = 'dt' === $group->name && '' !== $group->written ? $content : Inline::html('');
                $definitions[] = [];
            }

            if ('dt' !== $group->name || '' === $group->written) {
                $definitions[\count($terms) - 1][] = $content;
            }
        }

        if ([] === $terms) {
            return [];
        }

        $items = array_map(static fn (Inline $term, array $given): array => ['term' => $term, 'definitions' => [] === $given ? [Inline::html('')] : $given], $terms, $definitions);

        return [$this->context->block('definitionList', ['items' => $items])];
    }

    /**
     * The inline HTML of a tag that holds no block.
     */
    private function inline(Node $node, bool $inLink): string
    {
        return match ($node->name) {
            'b', 'i', 'u', 's', 'sup', 'sub', 'mark', 'highlight' => '<'.self::INLINE[$node->name].'>'.$this->content($node, $inLink).'</'.self::INLINE[$node->name].'>',
            'url' => $this->link($node, $inLink),
            'email' => $this->email($node, $inLink),
            'color' => $this->color($node, $inLink),
            'spoiler' => '<span class="spoiler">'.$this->content($node, $inLink).'</span>',
            'footnote' => $this->footnote($node),
            'icode' => '<code>'.self::escape(strtr($node->text(), "\n", ' ')).'</code>',
            'img' => $this->image($node, $inLink),
            'nbsp' => "\u{A0}",
            'ruby' => RubyRule::ruby($this->content($node, $inLink), self::escape($node->option ?? '')),
            'icon' => $this->icon($node),
            'hspace' => null === AttributeValue::Length->clean($node->option ?? '') ? self::escape($node->written) : '<span data-hspace="'.self::escape($node->option ?? '').'"></span>',
            'var' => isset($this->variables[$node->text()]) ? self::escape($this->variables[$node->text()]) : self::escape($node->written.$node->text().'[/var]'),
            default => $this->content($node, $inLink),
        };
    }

    /**
     * The inline HTML of what a tag holds, without the new line that opens it or the one that closes it.
     */
    private function content(Node $node, bool $inLink): string
    {
        $html = '';
        $last = \count($node->children) - 1;

        foreach ($node->children as $index => $child) {
            if ($child instanceof Node) {
                $html .= $this->inline($child, $inLink);

                continue;
            }

            $child = 0 === $index && str_starts_with($child, "\n") ? substr($child, 1) : $child;
            $child = $index === $last && str_ends_with($child, "\n") ? substr($child, 0, -1) : $child;
            $html .= $inLink ? self::escape($child) : Autolink::html($child);
        }

        return $html;
    }

    /**
     * A link to an address of a scheme `phil/ujo` allows — `http`, `https`, `mailto`, `tel`, `sms`, `geo`, the
     * messengers —, or a path of the site; `www.` takes `https://`. Any other address leaves the text alone, as does a
     * link inside a link.
     */
    private function link(Node $node, bool $inLink): string
    {
        $inner = $this->content($node, true);
        $href = $node->option ?? trim($node->text());
        $lower = strtolower($href);
        $href = str_starts_with($lower, 'www.') ? 'https://'.$href : $href;
        $safe = str_starts_with($lower, 'www.') || (null !== SafeUrl::scheme($href) && SafeUrl::isSafe($href)) || (str_starts_with($href, '/') && SafeUrl::isSafe($href));

        return $safe && !$inLink ? '<a href="'.self::escape($href).'">'.$inner.'</a>' : $inner;
    }

    private function email(Node $node, bool $inLink): string
    {
        $inner = $this->content($node, true);
        $address = $node->option ?? trim($node->text());

        return false === filter_var($address, \FILTER_VALIDATE_EMAIL) || $inLink ? $inner : '<a href="mailto:'.self::escape($address).'">'.$inner.'</a>';
    }

    /**
     * A color by its name, or `#rrggbb` written without its `#`, for the look to declare.
     */
    private function color(Node $node, bool $inLink): string
    {
        $color = ltrim($node->option ?? '', '#');
        $inner = $this->content($node, $inLink);

        return null === AttributeValue::Token->clean($color) ? $inner : '<span class="text-color" data-color="'.$color.'">'.$inner.'</span>';
    }

    /**
     * A footnote: its number where it is written, its text added at the end of the document.
     */
    private function footnote(Node $node): string
    {
        $label = \sprintf('bb%d', \count($this->context->footnotes));
        $this->context->footnotes[$label] = Inline::html('');
        $number = $this->context->footnote($label);
        $this->context->footnotes[$label] = $this->flat($node);

        return \sprintf('<sup data-footnote="fn-%1$d"><a href="#footnote-fn-%1$d">%1$d</a></sup>', $number);
    }

    /**
     * An image, `[img]address[/img]`, its alternative text the option or `alt` — BBCode gives an image no caption —,
     * its layout the attributes `width`, `height`, `wrap`, `align`, `id` and `class`: a mark the paragraph it stands
     * in is split around, its text alone inside a link, and the tag as written when the address is not that of an
     * image.
     */
    private function image(Node $node, bool $inLink): string
    {
        $address = trim($node->text());
        $text = $node->option ?? $node->attribute('alt');

        if (!SafeUrl::isSafe($address, SafeUrl::MEDIA) || '' === $address) {
            return self::escape($node->written.$node->text().'[/img]');
        }

        if ($inLink) {
            return self::escape('' === $text ? $address : $text);
        }

        $attributes = $this->attributes($node);
        $tokens = ['#'.$attributes->id, ...$attributes->classes];

        foreach ($attributes->styles as $property => $value) {
            $tokens[] = $property.':'.$value;
        }

        $key = 'i'.\count($this->context->images);
        $this->context->images[$key] = [$address, $text, implode(' ', $tokens), ''];

        return "\0".$key."\0";
    }

    /**
     * An icon, `[icon=set_name]` or `[icon=set:name]`, for the look to draw.
     */
    private function icon(Node $node): string
    {
        $icon = $node->option ?? '';
        $underscore = strpos($icon, '_');
        $icon = str_contains($icon, ':') || false === $underscore ? $icon : substr_replace($icon, ':', $underscore, 1);

        return null === AttributeValue::Reference->clean($icon) ? self::escape($node->written) : '<i data-icon="'.$icon.'"></i>';
    }
}
