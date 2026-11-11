<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Html;

use Dom\Attr;
use Dom\Comment;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Phil\Markup\Adapter\Autolink;
use Phil\Markup\Contract\ExtractedElementStore;
use Phil\Markup\Reader\BBCode\Flow;
use Phil\Markup\Reader\LossReason;
use Phil\Markup\Reader\Markdown\Attributes;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Layout;
use Phil\Markup\Reader\Tables;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use Phil\Ujo\Html\AttributeValue;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\LanguageTag;
use Phil\Ujo\Html\SafeUrl;
use Phil\Ujo\Type\EmbedServices;
use Phil\Ujo\Type\MediaTypes;

/**
 * The tree of an HTML document walked into UJO blocks, whose inline content is HTML already. Inline content between
 * blocks gathers into paragraphs; an element that holds blocks is read as blocks; a quote, a list item, a cell, a term
 * and a definition fold the blocks they hold into their inline content. Containers nest as deep as UJO allows, and
 * those deeper are left out, their blocks kept. The white space of text is collapsed as a browser collapses it, out
 * of preformatted text.
 *
 * A frame of a known service is an `embed` block, a video and a sound their players, a formula in MathML a `math`
 * block — inline, it stays in its paragraph —, a download link alone in its paragraph an `attaches` block, an inline
 * SVG drawing an image once purified and stored by the store of extracted elements. The language of an element is a
 * `language` tune on a block and a `<span lang>` inline; the `data-*` attributes of a block are its `data` tune and the
 * comments it holds its `note` tune. A table keeps its caption, its spans, its header cells and its alignments; the
 * blocks of a cell become lines of it, a list's items with their marks. What is not kept is recorded in the context.
 *
 * @internal
 */
final class Walker
{
    /** The depth below which a container of blocks may still hold, inside it, another one. */
    private const int MAX_NESTING = 7;

    /** The depth of the blocks folded into inline content. */
    private const int FLAT = 8;

    /** The depth of elements below which an element is read as its text alone. */
    private const int MAX_LEVEL = 128;

    private const array DROPPED = [...InlineSanitizer::DROPPED, 'head', 'meta', 'link', 'base', 'input', 'button', 'option', 'param', 'source', 'track', 'area', 'map'];

    /** The elements dropped without a word: what a page holds besides its text, and what the reader reads otherwise. */
    private const array SILENT = ['head', 'meta', 'link', 'base', 'title', 'input', 'option', 'param', 'source', 'track', 'area', 'map'];

    /** The elements that play something, each read as a block. */
    private const array PLAYERS = ['iframe', 'video', 'audio'];

    /** The extensions of the files a link alone in its paragraph attaches. */
    private const array FILES = ['pdf', 'epub', 'zip', '7z', 'rar', 'tar', 'gz', 'tgz', 'odt', 'ods', 'odp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'rtf', 'txt'];

    /** The `data-*` attributes the reader reads itself, or `phil/ujo` writes: never a `data` tune. */
    private const array OWN_DATA = [
        'data-checked', 'data-lines', 'data-mode', 'data-variant', 'data-footnote', 'data-icon', 'data-hspace', 'data-color',
        'data-mention', 'data-ref', 'data-tooltip', 'data-title', 'data-text', 'data-toc', 'data-notice-caption', 'data-size',
        'data-extension', 'data-qr-code', 'data-chart',
    ];

    /** The most entries of a `data` tune. */
    private const int MAX_DATA = 32;

    private const array BLOCKS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'menu', 'blockquote', 'figure', 'pre', 'hr', 'table', 'dl',
        'details', 'div', 'section', 'article', 'main', 'header', 'footer', 'nav', 'aside', 'center', 'address', 'hgroup',
        'fieldset',
    ];

    private const array HEADINGS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];

    private const array INLINE = [
        'b' => 'b', 'strong' => 'b', 'i' => 'i', 'em' => 'i', 'cite' => 'i', 'dfn' => 'i', 'var' => 'i', 'u' => 'u',
        'ins' => 'u', 's' => 's', 'del' => 's', 'strike' => 's', 'mark' => 'mark', 'code' => 'code', 'kbd' => 'code',
        'samp' => 'code', 'tt' => 'code', 'sup' => 'sup', 'sub' => 'sub',
    ];

    /** The classes `phil/ujo` gives the blocks it renders, by the type of each. */
    private const array TYPES = [
        'ujo-note' => 'note', 'ujo-descriptive' => 'descriptive', 'ujo-warning' => 'warning', 'ujo-alert' => 'alert',
        'ujo-wide' => 'wide', 'ujo-container' => 'container', 'ujo-columns' => 'columns', 'ujo-column-flow' => 'columnFlow',
        'ujo-page-break' => 'pageBreak', 'ujo-column-break' => 'columnBreak', 'ujo-page-style' => 'pageStyle',
        'ujo-spacer' => 'spacer', 'ujo-page-number' => 'pageNumber', 'ujo-decoration' => 'decoration',
        'ujo-running-footer' => 'runningFooter', 'ujo-watermark' => 'watermark', 'ujo-banner' => 'banner', 'ujo-toc' => 'toc',
        'ujo-credit' => 'credit', 'ujo-footnote' => 'footnote',
    ];

    private bool $inLink = false;

    private int $level = 0;

    private int $drawings = 0;

    public function __construct(
        private readonly Context $context,
        private readonly HTMLDocument $document,
        private readonly EmbedServices $services = new EmbedServices(),
        private readonly ?ExtractedElementStore $store = null,
        private readonly SvgSanitizer $sanitizer = new SvgSanitizer(),
    ) {
    }

    /**
     * The blocks an element holds, read at a depth of nesting.
     *
     * @return list<Block>
     */
    public function blocks(Node $parent, int $depth): array
    {
        $flow = new Flow($this->context);
        $notes = [];
        ++$this->level;

        foreach ($parent->childNodes as $child) {
            match (true) {
                $this->level > self::MAX_LEVEL => $flow->inline($this->deep($child)),
                $child instanceof Comment => $notes[] = trim($child->data),
                $child instanceof Element && $this->isBlock($child) => $this->place($flow, $child, $this->noted($this->block($child, $depth), $notes, false)),
                $child instanceof Element && $this->holdsBlock($child) => $flow->block(...$this->noted($this->blocks($child, $depth), $notes, false)),
                default => $flow->inline($this->inline($child)),
            };
        }

        --$this->level;
        $blocks = $this->noted($flow->blocks(), $notes, true);

        if ([] !== array_filter($notes, static fn (string $note): bool => '' !== $note)) {
            $this->context->lose('#comment', $this->path($parent), LossReason::Comment);
        }

        return $blocks;
    }

    /**
     * Places the blocks an element made in the flow; a player that made none leaves the paragraph it stands in whole.
     *
     * @param list<Block> $blocks
     */
    private function place(Flow $flow, Element $element, array $blocks): void
    {
        if ([] !== $blocks || !\in_array($element->localName, self::PLAYERS, true)) {
            $flow->block(...$blocks);
        }
    }

    /**
     * The blocks, the first — or the last — taking the comments met as its note; the comments stay when there is no
     * block.
     *
     * @param list<Block>  $blocks
     * @param list<string> $notes
     *
     * @return list<Block>
     */
    private function noted(array $blocks, array &$notes, bool $last): array
    {
        $text = implode("\n", array_filter($notes, static fn (string $note): bool => '' !== $note));
        $index = $last ? array_key_last($blocks) : array_key_first($blocks);

        if ('' === $text || null === $index) {
            return $blocks;
        }

        $notes = [];
        $note = $blocks[$index]->tunes['note'] ?? null;
        $earlier = \is_array($note) && \is_string($note['text'] ?? null) ? $note['text']."\n" : '';
        $noted = $blocks[$index]->withTunes([...$blocks[$index]->tunes, 'note' => ['text' => mb_substr($earlier.$text, 0, 10_000)]]);

        return [...\array_slice($blocks, 0, $index), $noted, ...\array_slice($blocks, $index + 1)];
    }

    /**
     * The text of a node nested deeper than is read, the loss recorded.
     */
    private function deep(Node $node): string
    {
        if ($node instanceof Element) {
            $this->context->lose($node->localName, $this->path($node), LossReason::Depth);
        }

        return $this->text($node);
    }

    /**
     * The path of a node in the page, `body > div[2] > p[1]`, each element counted among its siblings of its name.
     */
    private function path(Node $node): string
    {
        $parts = [];

        for ($element = $node instanceof Element ? $node : $node->parentElement; $element instanceof Element && 'html' !== $element->localName; $element = $element->parentElement) {
            $position = 1;

            for ($sibling = $element->previousElementSibling; $sibling instanceof Element; $sibling = $sibling->previousElementSibling) {
                $position += $sibling->localName === $element->localName ? 1 : 0;
            }

            $parts[] = 'body' === $element->localName ? 'body' : $element->localName.'['.$position.']';
        }

        return implode(' > ', array_reverse($parts));
    }

    /**
     * The text of a node, escaped, its white space collapsed, the elements left out dropped: what an element nested
     * deeper than is read leaves. The tree is walked with a stack of its own, however deep it goes.
     */
    private function text(Node $node): string
    {
        $text = '';
        $stack = [$node];

        while ([] !== $stack) {
            $next = array_pop($stack);

            if ($next instanceof Text) {
                $text .= $next->data;
            } elseif ($next instanceof Element && !\in_array($next->localName, self::DROPPED, true)) {
                foreach (array_reverse(iterator_to_array($next->childNodes)) as $child) {
                    $stack[] = $child;
                }
            }
        }

        return Autolink::escape(preg_replace('/[ \t\n\r\f]+/', ' ', $text) ?? '');
    }

    private function isBlock(Element $element): bool
    {
        return \in_array($element->localName, self::BLOCKS, true)
            || \in_array($element->localName, self::PLAYERS, true)
            || ('math' === $element->localName && 'block' === strtolower($element->getAttribute('display') ?? ''))
            || null !== $this->type($element);
    }

    private function holdsBlock(Element $element): bool
    {
        return !\in_array($element->localName, self::DROPPED, true) && array_any(iterator_to_array($element->children), $this->isBlock(...));
    }

    /**
     * The type of a block `phil/ujo` rendered, by its class; `null` for any other element.
     */
    private function type(Element $element): ?string
    {
        $class = array_find($this->classes($element), static fn (string $class): bool => isset(self::TYPES[$class]));

        return null === $class ? null : self::TYPES[$class];
    }

    /**
     * @return list<Block>
     */
    private function block(Element $element, int $depth): array
    {
        $name = $element->localName;
        $type = $this->type($element);
        $inner = $depth + 1 > self::MAX_NESTING ? null : $depth + 1;

        $made = match (true) {
            null !== $type => $this->rendered($element, $type, $depth),
            isset(self::HEADINGS[$name]) => [$this->context->block('header', ['text' => Inline::html(trim($this->content($element))), 'level' => self::HEADINGS[$name]])],
            'ul' === $name, 'ol' === $name, 'menu' === $name => $this->listing($element),
            'blockquote' === $name => [$this->context->block('quote', ['text' => $this->flat($element, '<br><br>'), 'caption' => Inline::html('')])],
            'figure' === $name => $this->figure($element, $depth),
            'pre' === $name => [$this->code($element)],
            'p' === $name && $this->attachment($element) instanceof Block => [$this->attachment($element)],
            'iframe' === $name => $this->frame($element),
            'video' === $name, 'audio' === $name => $this->player($element),
            'math' === $name => [$this->context->block('math', $this->formula($element))],
            'hr' === $name => [$this->context->block('delimiter', [])],
            'table' === $name => $this->table($element),
            'dl' === $name => $this->definitions($element),
            'details' === $name && null !== $inner => $this->toggle($element, $inner),
            'center' === $name => $this->align('center', $this->blocks($element, $depth)),
            default => $this->blocks($element, $depth),
        };

        return [] === $made ? [] : [Layout::apply($this->tuned($made[0], $element), $this->attributes($element), $this->context), ...\array_slice($made, 1)];
    }

    /**
     * A block with the language of its element, when it differs from the language around it, and its `data-*`
     * attributes.
     */
    private function tuned(Block $block, Element $element): Block
    {
        $tunes = $block->tunes;
        $language = $this->language($element);

        if (null !== $language[0]) {
            $tunes['language'] = ['language' => $language[0], ...null === $language[1] ? [] : ['direction' => $language[1]]];
        }

        $data = [];

        foreach ($element->attributes as $attribute) {
            $name = $attribute->name;

            if (str_starts_with($name, 'data-') && !\in_array($name, self::OWN_DATA, true) && \count($data) < self::MAX_DATA
                && 1 === preg_match('/^[a-z][a-z0-9-]{0,63}$/D', substr($name, 5)) && mb_strlen($attribute->value) <= 1000) {
                $data[substr($name, 5)] = $attribute->value;
            }
        }

        return $block->withTunes([] === $data ? $tunes : [...$tunes, 'data' => $data]);
    }

    /**
     * The language of an element when it differs from the language of its parent, and its direction; `null` for each
     * when it gives none, or none that is valid.
     *
     * @return array{?string, ?string}
     */
    private function language(Element $element): array
    {
        $language = $element->getAttribute('lang');
        $around = $element->parentElement?->closest('[lang]')?->getAttribute('lang');
        $direction = strtolower($element->getAttribute('dir') ?? ('bdi' === $element->localName ? 'auto' : ''));

        return [
            null !== $language && $language !== $around && LanguageTag::isValid($language) ? $language : null,
            \in_array($direction, ['ltr', 'rtl', 'auto'], true) ? $direction : null,
        ];
    }

    /**
     * The identifier, alignment and wrapping of an element, as UJO keeps them.
     */
    private function attributes(Element $element): Attributes
    {
        $align = strtolower($element->getAttribute('align') ?? '');
        $style = $this->style($element);
        $classes = [];

        foreach (['ujo-wrap-left' => 'wrapLeft', 'ujo-wrap-right' => 'wrapRight'] as $rendered => $class) {
            if ($element->classList->contains($rendered)) {
                $classes[] = $class;
            }
        }

        foreach (['left', 'center', 'right', 'justify'] as $alignment) {
            $align = $element->classList->contains('ujo-align-'.$alignment) ? $alignment : $align;
        }

        return new Attributes($element->getAttribute('id') ?? '', $classes, ['text-align' => $style['text-align'] ?? $align]);
    }

    /**
     * A block `phil/ujo` rendered, read back from its type; a container nested deeper than UJO allows is left out, its
     * blocks kept.
     *
     * @return list<Block>
     */
    private function rendered(Element $element, string $type, int $outer): array
    {
        $title = fn (string $class): string => $this->content($this->take($element, $class));
        $depth = $outer + 1;

        return match ($type) {
            'note', 'descriptive', 'wide', 'container', 'columns', 'columnFlow' => $depth > self::MAX_NESTING ? $this->blocks($element, $outer) : $this->container($element, $type, $depth),
            'warning' => [$this->context->block('warning', ['title' => Inline::html($title('ujo-warning__title')), 'message' => Inline::html($title('ujo-warning__message'))])],
            'alert' => [$this->context->block('alert', ['type' => $this->modifier($element, 'ujo-alert--') ?? 'info', 'text' => Inline::html($this->content($element))])],
            'pageStyle' => [$this->context->block('pageStyle', [...self::variant($element), 'classes' => $this->look($element)])],
            'spacer' => [$this->context->block('spacer', ['lines' => self::number($element->getAttribute('data-lines'), 1, 50, 1)])],
            'pageNumber' => [$this->context->block('pageNumber', self::pageNumber($element))],
            'decoration' => [$this->decoration($element)],
            'runningFooter' => [$this->context->block('runningFooter', ['text' => Inline::html($this->content($element))])],
            'watermark', 'banner' => [$this->context->block($type, ['text' => mb_substr(trim($element->textContent ?? ''), 0, 100)])],
            'toc' => [$this->context->block('toc', ['title' => mb_substr(trim($this->take($element, 'ujo-toc__title')->textContent ?? ''), 0, 200), 'depth' => 3, 'wide' => $element->classList->contains('ujo-wide')])],
            'credit' => [$this->context->block('credit', ['title' => mb_substr(trim($this->take($element, 'ujo-credit__title')->textContent ?? ''), 0, 200), 'text' => Inline::html(trim($this->content($element)))])],
            'footnote' => [],
            default => [$this->context->block($type, [])],
        };
    }

    /**
     * A container `phil/ujo` rendered: a box and its title, blocks across the columns, a class of the look, columns.
     *
     * @return list<Block>
     */
    private function container(Element $element, string $type, int $depth): array
    {
        $title = fn (string $class): string => $this->content($this->take($element, $class));

        return match ($type) {
            'note', 'descriptive' => [$this->context->block($type, ['title' => Inline::html($title('ujo-box__title')), 'blocks' => $this->blocks($element, $depth)])],
            'wide' => [$this->context->block('wide', ['blocks' => $this->blocks($element, $depth)])],
            'container' => [$this->context->block('container', ['classes' => $this->look($element), 'blocks' => $this->blocks($element, $depth)])],
            'columns' => [$this->context->block('columns', ['cols' => array_map(fn (Element $column): array => ['blocks' => $this->blocks($column, $depth)], iterator_to_array($element->children))])],
            default => [$this->context->block('columnFlow', ['count' => self::number($this->modifier($element, 'ujo-column-flow--'), 2, 6, 2), 'blocks' => $this->blocks($element, $depth)])],
        };
    }

    /**
     * The child of a class, taken out of its parent so that it is not read with the other children.
     */
    private function take(Element $element, string $class): ?Element
    {
        $found = array_find(iterator_to_array($element->children), static fn (Element $child): bool => $child->classList->contains($class));
        $found?->remove();

        return $found;
    }

    /**
     * What follows a prefix in the first class that starts with it.
     */
    private function modifier(Element $element, string $prefix): ?string
    {
        $class = array_find($this->classes($element), static fn (string $class): bool => str_starts_with($class, $prefix));

        return null === $class ? null : substr($class, \strlen($prefix));
    }

    /**
     * The classes of an element.
     *
     * @return array<int, string>
     */
    private function classes(Element $element): array
    {
        return array_filter(explode(' ', strtr($element->className, "\t\n\r\f", '    ')), static fn (string $class): bool => '' !== $class);
    }

    /**
     * The classes of an element that are not those of `phil/ujo`: the classes of the look.
     *
     * @return list<string>
     */
    private function look(Element $element): array
    {
        return array_values(array_filter($this->classes($element), static fn (string $class): bool => !str_starts_with($class, 'ujo-')));
    }

    /**
     * @return array{variant?: string}
     */
    private function variant(Element $element): array
    {
        $variant = $element->getAttribute('data-variant') ?? '';

        return '' === $variant ? [] : ['variant' => $variant];
    }

    /**
     * @return array{mode?: string, value?: string}
     */
    private function pageNumber(Element $element): array
    {
        $mode = $element->getAttribute('data-mode') ?? 'auto';

        return match ($mode) {
            'fixed' => ['mode' => 'fixed', 'value' => mb_substr(trim($element->textContent ?? ''), 0, 16)],
            'skip', 'reset' => ['mode' => $mode],
            default => [],
        };
    }

    private function decoration(Element $element): Block
    {
        $parts = explode('-', $this->modifier($element, 'ujo-decoration--') ?? '', 2);
        $color = $this->style($element)['--ujo-decoration-color'] ?? '';

        return $this->context->block('decoration', [
            'kind' => 'ornament' === $parts[0] ? 'ornament' : 'stain',
            'variant' => $this->number($parts[1] ?? null, 1, 99, 1),
            ...1 === preg_match('/^#[0-9a-f]{6}$/', $color) ? ['color' => $color] : [],
        ]);
    }

    /**
     * A figure: an image and its caption, a quote and its attribution, or the blocks it holds.
     *
     * @return list<Block>
     */
    private function figure(Element $element, int $depth): array
    {
        $children = iterator_to_array($element->children);
        $image = array_find($children, static fn (Element $child): bool => 'img' === $child->localName);
        $quote = array_find($children, static fn (Element $child): bool => 'blockquote' === $child->localName);
        $caption = array_find($children, static fn (Element $child): bool => 'figcaption' === $child->localName);
        $text = null === $caption ? '' : trim(InlineParser::text($this->content($caption)));

        $player = array_find($children, static fn (Element $child): bool => \in_array($child->localName, self::PLAYERS, true));

        return match (true) {
            $player instanceof Element => array_map(
                fn (Block $made): Block => 'paragraph' === $made->type ? $made : $this->played($made, $element, null === $caption ? '' : trim($this->content($caption))),
                'iframe' === $player->localName ? $this->frame($player) : $this->player($player),
            ),
            $image instanceof Element => [$this->context->block('paragraph', ['text' => Inline::html($this->image($image, $text, $element))])],
            $quote instanceof Element => [$this->context->block('quote', ['text' => $this->flat($quote, '<br><br>'), 'caption' => Inline::html(null === $caption ? '' : trim($this->content($caption)))])],
            default => $this->blocks($element, $depth),
        };
    }

    /**
     * The block a frame, a video or a sound in a figure made: its caption, and what `phil/ujo` wrote for paper — the
     * address of an embedded page, the poster, the title and the QR code — read back.
     */
    private function played(Block $made, Element $figure, string $caption): Block
    {
        $fallback = array_find(iterator_to_array($figure->children), static fn (Element $child): bool => 'a' === $child->localName && $child->classList->contains('ujo-print-fallback'));
        $data = [...$made->data, 'caption' => Inline::html($caption)];

        if ($fallback instanceof Element) {
            $source = trim($fallback->getAttribute('href') ?? '');
            $poster = trim($fallback->querySelector('img')?->getAttribute('src') ?? '');
            $title = trim($fallback->querySelector('.ujo-print-fallback__title')->textContent ?? '');
            $data = [
                ...$data,
                ...'embed' === $made->type && SafeUrl::isSafe($source, SafeUrl::MEDIA, false) ? ['source' => $source] : [],
                ...'audio' !== $made->type && '' !== $poster && SafeUrl::isSafe($poster, SafeUrl::MEDIA) ? ['poster' => $poster] : [],
                ...'' === $title ? [] : ['title' => mb_substr($title, 0, 200)],
                ...$figure->querySelector('.ujo-qr-code') instanceof Element ? ['qrCode' => true] : [],
            ];
        }

        return $made->withData($data);
    }

    /**
     * A frame: an `embed` block when its source is on a host of a known service, its link alone otherwise.
     *
     * @return list<Block>
     */
    private function frame(Element $frame): array
    {
        $source = trim($frame->getAttribute('src') ?? '');
        $service = array_find($this->services->names(), fn (string $name): bool => $this->services->allows($name, $source));
        $title = mb_substr(trim($frame->getAttribute('title') ?? ''), 0, 200);

        if (null === $service) {
            $this->context->lose('iframe', $this->path($frame), LossReason::UnknownEmbed);

            return SafeUrl::isSafe($source, SafeUrl::MEDIA, false) ? [$this->context->block('paragraph', ['text' => Inline::html('<a href="'.Autolink::escape($source).'">'.Autolink::escape('' === $title ? $source : $title).'</a>')])] : [];
        }

        return [$this->context->block('embed', [
            'service' => $service,
            'source' => $source,
            'embed' => $source,
            ...$this->size($frame),
            ...'' === $title || $title === $service ? [] : ['title' => $title],
        ])];
    }

    /**
     * The width and height of an element, when they are whole numbers of pixels.
     *
     * @return array{width?: int, height?: int}
     */
    private function size(Element $element): array
    {
        $size = [];

        foreach (['width', 'height'] as $side) {
            $value = filter_var($element->getAttribute($side), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10_000]]);

            if (false !== $value) {
                $size[$side] = $value;
            }
        }

        return $size;
    }

    /**
     * A video or a sound: its sources in order — its own `src`, then its `<source>` —, its text tracks, its poster and
     * its options; nothing when it has no source UJO accepts.
     *
     * @return list<Block>
     */
    private function player(Element $player): array
    {
        $sources = [];
        $tracks = [];

        foreach ([$player, ...iterator_to_array($player->children)] as $node) {
            $address = trim($node->getAttribute('src') ?? '');
            $kind = $node === $player || 'source' === $node->localName ? 'source' : ('track' === $node->localName ? 'track' : '');

            if ('' === $address || '' === $kind) {
                continue;
            }

            if (!SafeUrl::isSafe($address, SafeUrl::MEDIA)) {
                $this->context->lose($node->localName, $this->path($node), LossReason::RefusedScheme);
            } elseif ('source' === $kind && \count($sources) < 8) {
                $type = strtolower(trim($node->getAttribute('type') ?? ''));
                $sources[] = ['url' => $address, ...1 === preg_match(MediaTypes::MEDIA_TYPE, $type) && \strlen($type) <= 100 ? ['type' => $type] : []];
            } elseif ('track' === $kind && \count($tracks) < 16) {
                $tracks[] = $this->track($node, $address);
            }
        }

        if ([] === $sources) {
            $this->context->lose($player->localName, $this->path($player), LossReason::MissingSource);

            return [];
        }

        $poster = trim($player->getAttribute('poster') ?? '');
        $title = mb_substr(trim($player->getAttribute('title') ?? ''), 0, 200);

        return [$this->context->block($player->localName, [
            'sources' => $sources,
            'tracks' => $tracks,
            'controls' => $player->hasAttribute('controls'),
            'loop' => $player->hasAttribute('loop'),
            'muted' => $player->hasAttribute('muted'),
            ...'video' === $player->localName && SafeUrl::isSafe($poster, SafeUrl::MEDIA) ? ['poster' => $poster] : [],
            ...'video' === $player->localName ? $this->size($player) : [],
            ...'' === $title ? [] : ['title' => $title],
        ])];
    }

    /**
     * @return array{url: string, kind: string, language?: string, label?: string, default: bool}
     */
    private function track(Element $track, string $address): array
    {
        $kind = strtolower($track->getAttribute('kind') ?? '');
        $language = $track->getAttribute('srclang') ?? '';
        $label = mb_substr(trim($track->getAttribute('label') ?? ''), 0, 100);

        return [
            'url' => $address,
            'kind' => \in_array($kind, MediaTypes::TRACKS, true) ? $kind : 'subtitles',
            ...LanguageTag::isValid($language) ? ['language' => $language] : [],
            ...'' === $label ? [] : ['label' => $label],
            'default' => $track->hasAttribute('default'),
        ];
    }

    /**
     * A formula in MathML, with the LaTeX of its `application/x-tex` annotation when it has one.
     *
     * @return array{mathml: string, latex?: string}
     */
    private function formula(Element $math): array
    {
        $annotation = array_find(iterator_to_array($math->querySelectorAll('annotation')), static fn (Element $element): bool => 'application/x-tex' === strtolower($element->getAttribute('encoding') ?? ''));
        $latex = trim($annotation->textContent ?? '');

        return ['mathml' => $this->markup($math), ...'' === $latex ? [] : ['latex' => mb_substr($latex, 0, 20_000)]];
    }

    /**
     * An element and what it holds written back as markup — every attribute kept, for the sanitizer to choose —,
     * as deep as the reader reads.
     */
    private function markup(Node $node, string $root = ''): string
    {
        if ($node instanceof Text) {
            return Autolink::escape($node->data);
        }

        if (!$node instanceof Element || $this->level > self::MAX_LEVEL) {
            return '';
        }

        ++$this->level;
        $html = '<'.$node->localName.$root;

        foreach ($node->attributes as $attribute) {
            $html .= ' '.$attribute->name.'="'.Autolink::escape($attribute->value).'"';
        }

        $html .= '>';

        foreach ($node->childNodes as $child) {
            $html .= $this->markup($child);
        }

        --$this->level;

        return $html.'</'.$node->localName.'>';
    }

    /**
     * An inline SVG drawing: purified, stored by the store of extracted elements and shown as an image; left out,
     * and the loss recorded, without a store, or when the drawing or its address is refused.
     */
    private function drawing(Element $svg): string
    {
        if (!$this->store instanceof ExtractedElementStore) {
            $this->context->lose('svg', $this->path($svg), LossReason::SvgWithoutStore);

            return '';
        }

        $sanitized = $this->sanitizer->sanitize($this->markup($svg, $svg->hasAttribute('xmlns:xlink') ? '' : ' xmlns:xlink="http://www.w3.org/1999/xlink"'));
        $address = $sanitized->isRefused() ? null : $this->store->store($sanitized->svg, 'image/svg+xml', 'drawing-'.++$this->drawings.'.svg');

        if (null === $address || !SafeUrl::isSafe($address, SafeUrl::MEDIA)) {
            $this->context->lose('svg', $this->path($svg), LossReason::RefusedSvg);

            return '';
        }

        $title = array_find(iterator_to_array($svg->children), static fn (Element $child): bool => 'title' === $child->localName);
        $alt = trim($title->textContent ?? $svg->getAttribute('aria-label') ?? '');

        if ($this->inLink) {
            return Autolink::escape($alt);
        }

        $key = 'i'.\count($this->context->images);
        $this->context->images[$key] = [$address, $alt, '', ''];

        return "\0".$key."\0";
    }

    /**
     * A paragraph that holds only a link to a file — a download link, or a link to a document, an archive or a sheet
     * by its extension — as an `attaches` block; `null` for any other.
     */
    private function attachment(Element $paragraph): ?Block
    {
        $nodes = array_values(array_filter(iterator_to_array($paragraph->childNodes), static fn (Node $node): bool => !$node instanceof Comment && (!$node instanceof Text || '' !== trim($node->data))));
        $link = $nodes[0] ?? null;

        if (1 !== \count($nodes) || !$link instanceof Element || 'a' !== $link->localName) {
            return null;
        }

        $address = trim($link->getAttribute('href') ?? '');
        $download = $link->getAttribute('download');
        $path = explode('?', explode('#', $address)[0])[0];
        $named = basename(trim($download ?? ''));
        $name = mb_substr('' === $named ? rawurldecode(basename($path)) : $named, 0, 255);
        $extension = strtolower(pathinfo($name, \PATHINFO_EXTENSION));

        if (!SafeUrl::isSafe($address, SafeUrl::MEDIA) || (null === $download && !\in_array($extension, self::FILES, true))) {
            return null;
        }

        return $this->context->block('attaches', [
            'file' => ['url' => $address, ...'' === $name ? [] : ['name' => $name], ...'' === $extension || \strlen($extension) > 16 ? [] : ['extension' => $extension]],
            'title' => mb_substr(trim(preg_replace('/\s+/', ' ', $link->textContent ?? '') ?? ''), 0, 255),
        ]);
    }

    /**
     * Preformatted text, its language the class `language-…` of the code it holds.
     */
    private function code(Element $element): Block
    {
        $code = array_find(iterator_to_array($element->children), static fn (Element $child): bool => 'code' === $child->localName);
        $class = array_find($this->classes($code ?? $element), static fn (string $class): bool => str_starts_with($class, 'language-'));
        $language = null === $class ? '' : substr($class, 9);
        $valid = '' !== $language && \strlen($language) <= 32 && strspn($language, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_+#.-') === \strlen($language);

        return $this->context->block('code', ['code' => rtrim($element->textContent ?? '', "\n"), ...$valid ? ['language' => $language] : []]);
    }

    /**
     * A list, its items the `<li>` it holds — another element it holds is an item too —, a list inside an item its
     * children; a checklist when every item is checked or not, by `data-checked` or a checkbox.
     *
     * @return list<Block>
     */
    private function listing(Element $element): array
    {
        $items = iterator_to_array($element->children);
        $checks = array_map($this->checked(...), $items);
        $checklist = [] !== $items && !\in_array(null, $checks, true);
        $start = filter_var($element->getAttribute('start'), \FILTER_VALIDATE_INT);
        $counter = ['a' => 'lower-alpha', 'A' => 'upper-alpha', 'i' => 'lower-roman', 'I' => 'upper-roman'][$element->getAttribute('type') ?? ''] ?? null;

        if ([] === $items) {
            return [];
        }

        return [$this->context->block('list', [
            'style' => match (true) {
                $checklist => 'checklist',
                'ol' === $element->localName => 'ordered',
                default => 'unordered',
            },
            'meta' => match (true) {
                null !== $counter => ['counterType' => $counter],
                false !== $start && 1 !== $start && $start >= 0 => ['start' => $start],
                default => [],
            },
            'items' => array_map(fn (Element $item, ?bool $checked): array => $this->item($item, $checklist ? ['checked' => $checked] : []), $items, $checks),
        ])];
    }

    /**
     * Whether an item is checked: its `data-checked`, or a checkbox it holds; `null` when it says neither.
     */
    private function checked(Element $item): ?bool
    {
        $data = $item->getAttribute('data-checked');
        $box = array_find(iterator_to_array($item->children), static fn (Element $child): bool => 'input' === $child->localName && 'checkbox' === strtolower($child->getAttribute('type') ?? ''));

        return match (true) {
            null !== $data => 'true' === $data,
            $box instanceof Element => $box->hasAttribute('checked'),
            default => null,
        };
    }

    /**
     * @param array{checked?: bool|null} $meta
     *
     * @return array{content: Inline, meta: array{checked?: bool|null}, items: list<mixed>}
     */
    private function item(Element $element, array $meta): array
    {
        $children = [];
        $content = new Flow($this->context);
        ++$this->level;

        foreach ($element->childNodes as $child) {
            if ($this->level > self::MAX_LEVEL) {
                $content->inline($this->text($child));
            } elseif ($child instanceof Element && \in_array($child->localName, ['ul', 'ol'], true)) {
                $children = [...$children, ...BlockParser::items($this->listing($child)[0] ?? $this->context->block('list', []))];
            } elseif ($child instanceof Element && $this->isBlock($child)) {
                $content->block(...$this->block($child, self::FLAT));
            } else {
                $content->inline($this->inline($child));
            }
        }

        --$this->level;

        return ['content' => BlockParser::join($content->blocks(), '<br>'), 'meta' => $meta, 'items' => $children];
    }

    /**
     * A table, its rows those of its sections, its cells `<th>` and `<td>`, its rows made as long as the longest; a
     * first row of `<th>` only is its headings.
     *
     * @return list<Block>
     */
    private function table(Element $element): array
    {
        $rows = [];
        $headings = [];

        foreach ($this->rows($element) as $row) {
            $cells = iterator_to_array($row->children);
            $rows[] = array_map($this->cell(...), $cells);
            $headings[] = [] !== $cells && array_all($cells, static fn (Element $cell): bool => 'th' === $cell->localName);
        }

        if ([] === $rows) {
            return [];
        }

        $caption = array_find(iterator_to_array($element->children), static fn (Element $child): bool => 'caption' === $child->localName);
        $caption = null === $caption ? '' : trim($this->content($caption));

        return [$this->context->block('table', Tables::data($rows, $headings[0], '' === $caption ? null : Inline::html($caption)))];
    }

    /**
     * A cell: its content, the blocks it holds made lines — a list's items with their marks —, whether it is a header,
     * and its spans and alignment when it has them.
     *
     * @return array{text: Inline, header: bool, colspan?: int, rowspan?: int, align?: string}
     */
    private function cell(Element $cell): array
    {
        $parts = array_map(fn (Block $block): Block|Inline => 'list' === $block->type ? $this->lines(BlockParser::items($block), 'ordered' === ($block->data['style'] ?? null), 0) : $block, $this->blocks($cell, self::FLAT));
        $align = $cell->getAttribute('align') ?? $this->style($cell)['text-align'] ?? '';

        foreach (['left', 'center', 'right'] as $alignment) {
            $align = $cell->classList->contains('ujo-align-'.$alignment) ? $alignment : $align;
        }

        return [
            'text' => BlockParser::join($parts, '<br>'),
            'header' => 'th' === $cell->localName,
            ...Tables::layout($cell->getAttribute('colspan'), $cell->getAttribute('rowspan'), $align),
        ];
    }

    /**
     * The items of a list as lines, each after its mark — a bullet, or its number —, the nested ones indented.
     *
     * @param list<mixed> $items
     */
    private function lines(array $items, bool $ordered, int $depth): Inline
    {
        $lines = [];

        foreach ($items as $number => $item) {
            $item = \is_array($item) ? $item : [];
            $mark = str_repeat("\u{A0}\u{A0}", $depth).($ordered ? ($number + 1).'. ' : (0 === $depth ? '• ' : '◦ '));
            $lines[] = Inline::join([Inline::html(Autolink::escape($mark)), Inline::of($item['content'] ?? null)], '');
            $nested = \is_array($item['items'] ?? null) ? array_values($item['items']) : [];

            if ([] !== $nested) {
                $lines[] = $this->lines($nested, $ordered, $depth + 1);
            }
        }

        return Inline::join($lines, '<br>');
    }

    /**
     * @return list<Element>
     */
    private function rows(Element $table): array
    {
        $rows = [];

        foreach ($table->children as $section) {
            foreach ($section->children as $row) {
                if ('tr' === $row->localName) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * A definition list, its terms `<dt>` each followed by their definitions `<dd>`.
     *
     * @return list<Block>
     */
    private function definitions(Element $element): array
    {
        $items = [];

        foreach ($element->children as $child) {
            if ('dt' === $child->localName || ('dd' === $child->localName && [] === $items)) {
                $items[] = ['term' => 'dt' === $child->localName ? $this->flat($child, '<br>') : Inline::html(''), 'definitions' => []];
            }

            if ('dd' === $child->localName) {
                $items[array_key_last($items)]['definitions'][] = $this->flat($child, '<br>');
            }
        }

        $items = array_map(static fn (array $item): array => [...$item, 'definitions' => [] === $item['definitions'] ? [Inline::html('')] : $item['definitions']], $items);

        return [] === $items ? [] : [$this->context->block('definitionList', ['items' => $items])];
    }

    /**
     * Details, its summary the text of a toggle.
     *
     * @return list<Block>
     */
    private function toggle(Element $element, int $depth): array
    {
        $summary = array_find(iterator_to_array($element->children), static fn (Element $child): bool => 'summary' === $child->localName);
        $summary?->remove();

        return [$this->context->block('toggle', [
            'text' => Inline::html(null === $summary ? '' : trim($this->content($summary))),
            'open' => $element->hasAttribute('open'),
            'blocks' => $this->blocks($element, $depth),
        ])];
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private function align(string $alignment, array $blocks): array
    {
        return array_map(static fn (Block $block): Block => $block->withTunes([...$block->tunes, 'alignment' => ['alignment' => $alignment]]), $blocks);
    }

    /**
     * The blocks an element holds folded into inline content, a separator between two.
     */
    private function flat(Element $element, string $separator): Inline
    {
        return BlockParser::join($this->blocks($element, self::FLAT), $separator);
    }

    /**
     * The inline HTML of the children of an element.
     */
    private function content(?Element $element): string
    {
        $html = '';
        ++$this->level;

        foreach ($element->childNodes ?? [] as $child) {
            $html .= $this->level > self::MAX_LEVEL ? $this->text($child) : $this->inline($child);
        }

        --$this->level;

        return $html;
    }

    /**
     * The inline HTML of a node: text escaped, its white space collapsed; an element UJO keeps as its element, any
     * other as its content.
     */
    private function inline(Node $node): string
    {
        if ($node instanceof Text) {
            return $this->text($node);
        }

        if ($node instanceof Comment && '' !== trim($node->data)) {
            $this->context->lose('#comment', $this->path($node), LossReason::Comment);
        }

        if (!$node instanceof Element || $node->classList->contains('ujo-print-fallback')) {
            return '';
        }

        $name = $node->localName;

        if ('math' === $name || 'svg' === $name) {
            return $this->spoken($node, 'math' === $name ? $this->markup($node) : $this->drawing($node));
        }

        if (\in_array($name, self::DROPPED, true)) {
            if (!\in_array($name, self::SILENT, true)) {
                $this->context->lose($name, $this->path($node), LossReason::UnknownElement);
            }

            return '';
        }

        if (array_any(iterator_to_array($node->attributes), static fn (Attr $attribute): bool => str_starts_with($attribute->name, 'data-') && !\in_array($attribute->name, self::OWN_DATA, true))) {
            $this->context->lose($name, $this->path($node), LossReason::DataAttribute);
        }

        return $this->spoken($node, $this->element($node, $name));
    }

    /**
     * The HTML of an inline element, wrapped in a `<span lang dir>` when it gives a language other than the one around
     * it, or a direction.
     */
    private function spoken(Element $element, string $html): string
    {
        [$language, $direction] = $this->language($element);

        return null === $language && null === $direction ? $html : '<span'.(null === $language ? '' : ' lang="'.Autolink::escape($language).'"').(null === $direction ? '' : ' dir="'.$direction.'"').'>'.$html.'</span>';
    }

    /**
     * The HTML of an inline element UJO keeps or reads.
     */
    private function element(Element $node, string $name): string
    {
        return match (true) {
            'br' === $name => '<br>',
            'abbr' === $name, 'acronym' === $name => $this->abbreviation($node),
            'ruby' === $name => $this->ruby($node),
            'img' === $name => $this->image($node, '', $node),
            'a' === $name => $this->link($node),
            $node->hasAttribute('data-footnote') => $this->footnote($node->getAttribute('data-footnote') ?? ''),
            $node->hasAttribute('data-icon') => $this->icon($node->getAttribute('data-icon') ?? '') ?? $this->content($node),
            $node->hasAttribute('data-hspace') => null === AttributeValue::Length->clean($node->getAttribute('data-hspace') ?? '') ? '' : '<span data-hspace="'.Autolink::escape($node->getAttribute('data-hspace') ?? '').'"></span>',
            $node->classList->contains('spoiler') => '<span class="spoiler">'.$this->content($node).'</span>',
            $node->hasAttribute('data-color') => $this->color($node->getAttribute('data-color') ?? '', $this->content($node)),
            isset(self::INLINE[$name]) && !$this->plain($node) => '<'.self::INLINE[$name].'>'.$this->content($node).'</'.self::INLINE[$name].'>',
            'q' === $name => '“'.$this->content($node).'”',
            default => $this->styled($node, $this->content($node)),
        };
    }

    /**
     * An abbreviation and its meaning, its content alone without one.
     */
    private function abbreviation(Element $element): string
    {
        $title = trim($element->getAttribute('title') ?? '');

        return '' === $title || null === AttributeValue::Text->clean($title) ? $this->content($element) : '<abbr title="'.Autolink::escape($title).'">'.$this->content($element).'</abbr>';
    }

    /**
     * A ruby: its base, and each reading `<rt>` written between the parentheses of `<rp>` for the browsers that do not
     * show it above.
     */
    private function ruby(Element $ruby): string
    {
        $html = '';
        ++$this->level;

        foreach ($ruby->childNodes as $child) {
            $html .= match (true) {
                $this->level > self::MAX_LEVEL => $this->text($child),
                $child instanceof Element && 'rt' === $child->localName => '<rp>(</rp><rt>'.$this->content($child).'</rt><rp>)</rp>',
                $child instanceof Element && 'rp' === $child->localName => '',
                default => $this->inline($child),
            };
        }

        --$this->level;

        return '<ruby>'.$html.'</ruby>';
    }

    /**
     * Whether bold is undone by its style, as a word processor wraps a whole paste in `<b style="font-weight:normal">`.
     */
    private function plain(Element $element): bool
    {
        return \in_array($this->style($element)['font-weight'] ?? '', ['normal', '400'], true);
    }

    /**
     * The HTML of an element without an element of its own, wrapped in the elements its style asks for: bold, italic,
     * underline, strikethrough.
     */
    private function styled(Element $element, string $html): string
    {
        $style = $this->style($element);
        $decoration = $style['text-decoration'] ?? $style['text-decoration-line'] ?? '';
        $tags = [
            'b' => \in_array($style['font-weight'] ?? '', ['bold', 'bolder', '600', '700', '800', '900'], true),
            'i' => 'italic' === ($style['font-style'] ?? ''),
            'u' => str_contains($decoration, 'underline'),
            's' => str_contains($decoration, 'line-through'),
        ];

        foreach (array_keys(array_filter($tags)) as $tag) {
            $html = '<'.$tag.'>'.$html.'</'.$tag.'>';
        }

        return $html;
    }

    /**
     * The declarations of the style of an element, by property in lower case.
     *
     * @return array<string, string>
     */
    private function style(Element $element): array
    {
        $style = [];

        foreach (explode(';', $element->getAttribute('style') ?? '') as $declaration) {
            $pair = explode(':', $declaration, 2);

            if (isset($pair[1])) {
                $style[strtolower(trim($pair[0]))] = strtolower(trim($pair[1]));
            }
        }

        return $style;
    }

    /**
     * A link to a web or e-mail address or a path of the site; its content alone for any other address, and inside a
     * link, where an image is its text.
     */
    private function link(Element $element): string
    {
        $href = trim($element->getAttribute('href') ?? '');
        $this->inLink = true;
        $html = $this->content($element);
        $this->inLink = false;
        $reason = match (true) {
            '' === $href => null,
            str_starts_with($href, '#') => $this->document->getElementById(substr($href, 1)) instanceof Element ? null : LossReason::UnknownAnchor,
            default => SafeUrl::isSafe($href) ? null : LossReason::RefusedScheme,
        };

        if (null !== $reason) {
            $this->context->lose('a', $this->path($element), $reason);
        }

        return null === $reason && '' !== $href ? '<a href="'.Autolink::escape($href).'">'.$html.'</a>' : $html;
    }

    /**
     * An image: a mark the paragraph it stands in is split around, its alternative text its `alt`, its caption the
     * `<figcaption>` given or else its `title`, its layout the size, alignment and wrapping of the image or of the
     * figure that holds it; in a link, or without an address, its alternative text or else its caption.
     */
    private function image(Element $image, string $caption, Element $holder): string
    {
        $address = trim($image->getAttribute('src') ?? '');
        $alt = trim($image->getAttribute('alt') ?? '');
        $caption = '' === $caption ? trim($image->getAttribute('title') ?? '') : $caption;

        if ($this->inLink || '' === $address) {
            return Autolink::escape('' === $alt ? $caption : $alt);
        }

        $attributes = $this->attributes($holder);
        $style = [...$this->style($image), ...$this->style($holder)];
        $tokens = [...'' === $attributes->id ? [] : ['#'.$attributes->id], ...$attributes->classes];
        $align = strtolower($image->getAttribute('align') ?? '');
        $tokens = [...$tokens, ...['left' => ['wrapLeft'], 'right' => ['wrapRight']][$align] ?? []];

        foreach (['width', 'height'] as $side) {
            $size = $style[$side] ?? $image->getAttribute($side) ?? '';
            $tokens = [...$tokens, ...'' === $size ? [] : [$side.':'.(ctype_digit($size) ? $size.'px' : $size)]];
        }

        $key = 'i'.\count($this->context->images);
        $this->context->images[$key] = [$address, $alt, implode(' ', $tokens), $caption];

        return "\0".$key."\0";
    }

    /**
     * A reference to a footnote `phil/ujo` rendered, its text the footnote of the document whose identifier it names.
     */
    private function footnote(string $identifier): string
    {
        $label = $identifier;

        if (!isset($this->context->footnotes[$label])) {
            $this->context->footnotes[$label] = Inline::html('');
            $this->context->footnote($label);
            $this->context->footnotes[$label] = Inline::html(trim($this->content($this->document->getElementById('footnote-'.$identifier))));
        }

        return \sprintf('<sup data-footnote="fn-%1$d"><a href="#footnote-fn-%1$d">%1$d</a></sup>', $this->context->footnote($label));
    }

    private function number(?string $value, int $minimum, int $maximum, int $default): int
    {
        $number = filter_var($value, \FILTER_VALIDATE_INT);

        return false === $number ? $default : max($minimum, min($maximum, $number));
    }

    private function icon(string $icon): ?string
    {
        return null === AttributeValue::Reference->clean($icon) ? null : '<i data-icon="'.$icon.'"></i>';
    }

    private function color(string $color, string $html): string
    {
        return null === AttributeValue::Token->clean($color) ? $html : '<span class="text-color" data-color="'.$color.'">'.$html.'</span>';
    }
}
