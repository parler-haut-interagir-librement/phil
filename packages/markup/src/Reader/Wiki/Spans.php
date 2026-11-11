<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Adapter\Autolink;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Ujo\Html\AttributeValue;

/**
 * The inline text of a wiki as the inline HTML of UJO. The text is read by the shared cursor: runs of plain text are
 * escaped, web addresses are made links, the extension tags every wiki shares — `<sup>`, `<ref>`, `<<footnote>>`… —
 * become their elements, and the characters of the wiki's own syntax are lexed by it. Marks of emphasis are paired as
 * they come: a mark closes the element of its kind still open, the elements opened inside it and left open staying
 * text, so that the HTML is always well nested; a mark never closed is text.
 *
 * Content is read at most eight levels deep, and the delimiters searched for are remembered when they are missing, so
 * that any text is read in linear time. An image is left as a mark, `\0n\0`, for the block it stands in to place.
 *
 * @internal
 */
final class Spans
{
    /** The depth below which the content of an element is still read. */
    private const int MAX_DEPTH = 8;

    /** The bytes a link, an image or a template is searched in. */
    private const int WINDOW = 4096;

    /** The tags whose content is read, by the element they make; `''` keeps the content alone. */
    private const array ELEMENTS = [
        'b' => 'b', 'strong' => 'b', 'i' => 'i', 'em' => 'i', 'u' => 'u', 'ins' => 'u', 's' => 's', 'del' => 's',
        'strike' => 's', 'sup' => 'sup', 'sub' => 'sub', 'mark' => 'mark', 'tt' => 'code', 'kbd' => 'code', 'span' => '',
        'small' => '', 'big' => '',
    ];

    /** The beginnings of a web address in the text. */
    private const array ADDRESSES = ['http://', 'https://', 'www.'];

    /** The tags that hold nothing. */
    private const array VOID = ['br', 'nbsp', 'icon', 'hspace', 'var', 'references'];

    /** @var array<string, string> the value of each variable, by name in lower case */
    public array $variables = [];

    public readonly Dialect $dialect;

    private readonly string $stops;

    private int $depth = 0;

    private bool $inLink = false;

    /** @var array<string, int> the first position a delimiter was searched from and not found, by delimiter */
    private array $missing = [];

    /** @var list<Toggle> the marks of emphasis open, the innermost last */
    private array $open = [];

    public function __construct(
        public readonly Context $context,
        private readonly Syntax $syntax,
        public readonly WikiLinks $links,
    ) {
        $this->dialect = $syntax->dialect();
        $this->stops = 'hHwW'.$this->dialect->opener()[0].$syntax->triggers();
    }

    public static function escape(string $text): string
    {
        return Autolink::escape($text);
    }

    /**
     * The inline HTML of a text; `$inLink` for the text of a link, where neither links nor images are made.
     */
    public function parse(string $text, bool $inLink = false): string
    {
        if ($this->depth >= self::MAX_DEPTH) {
            return self::escape($text);
        }

        $outer = [$this->inLink, $this->missing, $this->open];
        $this->inLink = $inLink || $this->inLink;
        $this->missing = [];
        $this->open = [];
        ++$this->depth;
        $cursor = new Cursor($text);
        $html = [];

        while (!$cursor->isEnd()) {
            $run = $cursor->consumeUntil($this->stops);

            foreach ('' === $run ? $this->tokens($cursor) : [self::escape($run)] as $token) {
                if ($token instanceof Toggle) {
                    $this->pair($token);
                }

                $html[] = $token;
            }
        }

        --$this->depth;
        [$this->inLink, $this->missing, $this->open] = $outer;

        return implode('', array_map(static fn (Toggle|string $token): string => \is_string($token) ? $token : $token->html(), $html));
    }

    /**
     * Whether the text being read is the text of a link.
     */
    public function inLink(): bool
    {
        return $this->inLink;
    }

    /**
     * The kind of the innermost mark of emphasis still open; `null` when none is.
     */
    public function top(): ?string
    {
        return array_last($this->open)?->kind;
    }

    /**
     * The text up to a delimiter, in any case, consumed with it; `null`, and nothing consumed, when it never comes. A
     * delimiter missing from a position is missing from every position after it, which is remembered.
     */
    public function through(Cursor $cursor, string $close): ?string
    {
        $from = $cursor->position();
        $at = ($this->missing[$close] ?? \PHP_INT_MAX) <= $from ? false : stripos($cursor->text, $close, $from);

        if (false === $at) {
            $this->missing[$close] = $from;

            return null;
        }

        $cursor->moveTo($at + \strlen($close));

        return substr($cursor->text, $from, $at - $from);
    }

    /**
     * The content of a link, an image or a template, up to the delimiter that closes it, those it holds — three at
     * most — closed inside it; `null` when it is not closed within 4 KB, the cursor then left for the caller to move
     * back.
     */
    public function balanced(Cursor $cursor, string $open, string $close): ?string
    {
        $content = $this->within($cursor, $close);

        for ($round = 0; null !== $content && substr_count($content, $open) > substr_count($content, $close) && $round < 3; ++$round) {
            $more = $this->within($cursor, $close);
            $content = null === $more ? null : $content.$close.$more;
        }

        return null === $content || substr_count($content, $open) > substr_count($content, $close) ? null : $content;
    }

    /**
     * A link around some HTML, an address `www.…` taking `https://`. The normalizer of `phil/ujo` the document goes
     * through keeps the HTML alone of a link inside a link, or to an address that is not safe.
     */
    public function link(string $href, string $html): string
    {
        $href = str_starts_with(strtolower($href), 'www.') ? 'https://'.$href : $href;

        return '<a href="'.self::escape($href).'">'.$html.'</a>';
    }

    /**
     * An image, its alternative text and its caption each empty when the markup gives none: its mark in the text, or
     * its text alone in the text of a link — its alternative text, its caption or its address.
     */
    public function image(string $address, string $alt, string $attributes, string $caption): string
    {
        if ($this->inLink) {
            return self::escape(array_find([$alt, $caption], static fn (string $text): bool => '' !== $text) ?? $address);
        }

        $key = 'i'.\count($this->context->images);
        $this->context->images[$key] = [$address, $alt, $attributes, $caption];

        return "\0".$key."\0";
    }

    /**
     * A footnote: its number where it is written, its text added at the end of the document. A named footnote is
     * defined once and referenced again by its name; `null` for a name never defined.
     */
    public function footnote(string $name, ?string $content): ?string
    {
        $label = '' === $name ? \sprintf('w%d', \count($this->context->footnotes)) : \sprintf('ref:%s', $name);

        if (!isset($this->context->footnotes[$label])) {
            if (null === $content) {
                return null;
            }

            $this->context->footnotes[$label] = Inline::html('');
            $this->context->footnote($label);
            $this->context->footnotes[$label] = Inline::html(trim($this->parse($content)));
        }

        return \sprintf('<sup data-footnote="fn-%1$d"><a href="#footnote-fn-%1$d">%1$d</a></sup>', $this->context->footnote($label));
    }

    /**
     * The HTML of an extension every wiki shares, written as a tag or, in MediaWiki, as a template: its content, when
     * it holds one, as written; `null` when the extension is not known or lacks its content.
     */
    public function extension(Tag $tag, ?string $content): ?string
    {
        $first = $tag->first() ?? '';

        return match ($tag->name) {
            'br' => '<br>',
            'nbsp' => "\u{A0}",
            'references' => '',
            'icon' => $this->icon($first),
            'hspace' => null === AttributeValue::Length->clean($first) ? null : '<span data-hspace="'.self::escape($first).'"></span>',
            'var' => $this->variable($tag),
            'ref', 'footnote' => $this->footnote($tag->attribute('name'), $content),
            default => null === $content ? null : $this->content($tag, $content),
        };
    }

    /**
     * The HTML of a tag that holds content.
     */
    private function content(Tag $tag, string $content): ?string
    {
        return match ($tag->name) {
            'code' => '<code>'.self::escape(strtr($content, "\n", ' ')).'</code>',
            'nowiki' => self::escape($content),
            'html' => $this->context->rawHtml ? $content : self::escape($content),
            'spoiler' => '<span class="spoiler">'.$this->parse($content).'</span>',
            'color' => $this->color('' === $tag->attribute('color') ? $tag->first() ?? '' : $tag->attribute('color'), $this->parse($content)),
            default => isset(self::ELEMENTS[$tag->name]) ? $this->wrap(self::ELEMENTS[$tag->name], $this->parse($content)) : null,
        };
    }

    /**
     * @return list<Toggle|string>
     */
    private function tokens(Cursor $cursor): array
    {
        $made = $this->dialect->opener()[0] === $cursor->current()
            ? $this->tag($cursor) ?? $this->syntax->inline($cursor, $this)
            : $this->address($cursor) ?? $this->syntax->inline($cursor, $this);

        return match (true) {
            null === $made => [self::escape($cursor->advance())],
            \is_array($made) => $made,
            default => [$made],
        };
    }

    /**
     * Pairs a mark of emphasis: it closes the mark of its kind still open, and the marks opened after that one stay
     * text; otherwise it opens. A kind is open once at most, so the marks open are few.
     */
    private function pair(Toggle $toggle): void
    {
        $found = array_find_key($this->open, static fn (Toggle $open): bool => $open->kind === $toggle->kind);

        if (null === $found) {
            $this->open[] = $toggle;

            return;
        }

        $this->open[$found]->opens = true;
        $toggle->opens = false;
        $this->open = \array_slice($this->open, 0, $found);
    }

    /**
     * A web address in the text, `https://…`, `http://…` or `www.…`, made a link; the punctuation that ends a sentence
     * stays out of it, and an address left with its beginning alone is text.
     */
    private function address(Cursor $cursor): ?string
    {
        $start = $cursor->position();
        $prefix = array_find(self::ADDRESSES, static fn (string $prefix): bool => 0 === substr_compare($cursor->text, $prefix, $start, \strlen($prefix), true));

        if (null === $prefix || ctype_alnum($cursor->peek(-1))) {
            return null;
        }

        $address = rtrim($cursor->consumeUntil(" \t\n<>\"[]{}|"), ".,;:!?)'");

        if (0 !== substr_compare($address, $prefix, 0, \strlen($prefix), true)) {
            $cursor->moveTo($start);

            return null;
        }

        $cursor->moveTo($start + \strlen($address));

        return $this->link($address, self::escape($address));
    }

    /**
     * An extension tag in the text, `<sup>…</sup>`, `<<footnote>>…<</footnote>>`, `<br>`; `null`, the cursor where it
     * was, for a tag not known or left open.
     */
    private function tag(Cursor $cursor): ?string
    {
        $start = $cursor->position();

        if (!$cursor->comesNext($this->dialect->opener())) {
            return null;
        }

        $cursor->advance(\strlen($this->dialect->opener()));

        $inside = $this->through($cursor, $this->dialect->closer());
        $tag = null === $inside ? null : Tag::parse($inside);
        $html = !$tag instanceof Tag || $tag->closing ? null : $this->element($tag, $cursor);

        if (null === $html) {
            $cursor->moveTo($start);
        }

        return $html;
    }

    private function element(Tag $tag, Cursor $cursor): ?string
    {
        if ($tag->void || \in_array($tag->name, self::VOID, true)) {
            return $this->extension($tag, null);
        }

        $content = $this->through($cursor, $this->dialect->closing($tag->name));

        return null === $content ? null : $this->extension($tag, $content);
    }

    /**
     * The text up to a delimiter within 4 KB, consumed with it; `null`, and nothing consumed, when it is not there.
     */
    private function within(Cursor $cursor, string $close): ?string
    {
        $from = $cursor->position();
        $at = strpos(substr($cursor->text, $from, self::WINDOW), $close);

        if (false === $at) {
            return null;
        }

        $cursor->moveTo($from + $at + \strlen($close));

        return substr($cursor->text, $from, $at);
    }

    /**
     * A variable: its value where it is used, `<var name>`; defined, `<var name="value">`, it leaves nothing.
     */
    private function variable(Tag $tag): ?string
    {
        foreach ($tag->attributes as $name => $value) {
            $this->variables[$name] = $value;
        }

        $name = strtolower($tag->first() ?? '');

        return match (true) {
            [] !== $tag->attributes => '',
            isset($this->variables[$name]) => self::escape($this->variables[$name]),
            default => null,
        };
    }

    /**
     * An icon, `set_name` or `set:name`, for the look to draw.
     */
    private function icon(string $icon): ?string
    {
        $underscore = strpos($icon, '_');
        $icon = str_contains($icon, ':') || false === $underscore ? $icon : substr_replace($icon, ':', $underscore, 1);

        return null === AttributeValue::Reference->clean($icon) ? null : '<i data-icon="'.$icon.'"></i>';
    }

    /**
     * A color by its name, or `#rrggbb` written with or without its `#`, for the look to declare.
     */
    private function color(string $color, string $html): string
    {
        $color = ltrim($color, '#');

        return null === AttributeValue::Token->clean($color) ? $html : '<span class="text-color" data-color="'.$color.'">'.$html.'</span>';
    }

    private function wrap(string $tag, string $html): string
    {
        return '' === $tag ? $html : '<'.$tag.'>'.$html.'</'.$tag.'>';
    }
}
