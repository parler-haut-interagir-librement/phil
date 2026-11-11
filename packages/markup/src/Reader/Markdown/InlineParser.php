<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Rule\AngleRule;
use Phil\Markup\Reader\Markdown\Rule\BareLinkRule;
use Phil\Markup\Reader\Markdown\Rule\CodeSpanRule;
use Phil\Markup\Reader\Markdown\Rule\DelimiterRule;
use Phil\Markup\Reader\Markdown\Rule\EntityRule;
use Phil\Markup\Reader\Markdown\Rule\EscapeRule;
use Phil\Markup\Reader\Markdown\Rule\InlineRule;
use Phil\Markup\Reader\Markdown\Rule\LinkRule;
use Phil\Markup\Reader\Markdown\Rule\RubyRule;

/**
 * Inline Markdown as the inline HTML of UJO. The text is lexed into tokens by rules, each started by its own
 * characters, the text between them escaped; the delimiter runs are then paired into emphasis as CommonMark pairs
 * them, from the first closer, with the nearest opener of the same character. A new line after two spaces is a line
 * break. An image is left as a mark, `\0n\0`, for the block it stands in to place. The rules of a dialect come after
 * the built-in ones; a rule reads nested Markdown with `parse()`, and escapes its text with `escape()`.
 */
final class InlineParser
{
    private const array TAGS = ['*' => [1 => 'i', 2 => 'b'], '_' => [1 => 'i', 2 => 'b'], '~' => [1 => 's', 2 => 's'], '=' => [2 => 'mark'], '^' => [1 => 'sup', 2 => 'sub']];

    /** @var array<string, list<InlineRule>> */
    private array $rules = [];

    private string $stops = "\n";

    private bool $inLink = false;

    public function __construct(
        public readonly Context $context,
    ) {
        $rules = [new EscapeRule(), new EntityRule(), new CodeSpanRule(), new AngleRule(), new LinkRule(), new BareLinkRule(), new DelimiterRule(), ...$context->dialect?->inlineRules() ?? [], new RubyRule()];

        foreach ($rules as $rule) {
            foreach (str_split($rule->triggers()) as $character) {
                $this->rules[$character][] = $rule;
                $this->stops .= $character;
            }
        }
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function link(string $address, string $html): string
    {
        return '<a href="'.self::escape($address).'">'.$html.'</a>';
    }

    /**
     * The address of a link as written, its backslash escapes and entities read, its spaces, backslashes, quotes and
     * angle brackets encoded.
     */
    public static function destination(string $address): string
    {
        $address = preg_replace('/\\\\([!-\/:-@\[-`{-~])/', '$1', $address) ?? $address;

        return strtr(html_entity_decode($address, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), [' ' => '%20', '\\' => '%5C', '"' => '%22', '<' => '%3C', '>' => '%3E']);
    }

    /**
     * The title of a link or an image, `"title"`, `'title'` or `(title)` without its quotes: its escapes and entities
     * read.
     */
    public static function title(string $title): string
    {
        return html_entity_decode(preg_replace('/\\\\([!-\/:-@\[-`{-~])/', '$1', $title) ?? $title, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * The text an image stands for where it cannot be shown — in a link, a heading, a plain field —: its alternative
     * text, or its caption without one.
     *
     * @param array{string, string, string, string} $image
     */
    public static function imageText(array $image): string
    {
        return '' === $image[1] ? $image[3] : $image[1];
    }

    /**
     * The text of some inline HTML, its elements left out and its entities read.
     */
    public static function text(string $html): string
    {
        return html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * Whether the text being read is the text of a link, where neither links nor images are made.
     */
    public function inLink(): bool
    {
        return $this->inLink;
    }

    public function html(Inline $inline): string
    {
        $html = '';

        foreach ($inline->pieces as [$isHtml, $text]) {
            $html .= $isHtml ? $text : $this->parse($text);
        }

        return $html;
    }

    public function parse(string $markdown, bool $inLink = false): string
    {
        $outer = $this->inLink;
        $this->inLink = $inLink || $outer;
        $cursor = new Cursor($markdown);
        $nodes = [];

        while (!$cursor->isEnd()) {
            $text = $cursor->consumeUntil($this->stops);

            if ('' !== $text) {
                $nodes[] = self::escape($text);
            } elseif ($cursor->consume("\n")) {
                $this->newLine($nodes);
            } else {
                $nodes[] = $this->token($cursor) ?? self::escape($cursor->advance());
            }
        }

        $this->inLink = $outer;

        return $this->pair($nodes);
    }

    /**
     * An image: its mark in the text, or in the text of a link its alternative text, or else its caption. Its label is
     * its alternative text, its title its caption.
     */
    public function image(string $address, string $label, string $attributes, string $title = ''): string
    {
        $text = self::text($this->parse($label, true));

        if ($this->inLink) {
            return self::escape('' === $text ? $title : $text);
        }

        $key = 'i'.\count($this->context->images);
        $this->context->images[$key] = [$address, $text, $attributes, $title];

        return "\0".$key."\0";
    }

    /**
     * The plain text of inline content, an image as its text.
     */
    public function plain(Inline $inline): string
    {
        return self::text($this->images($this->html($inline), static fn (array $image): string => self::escape(self::imageText($image))));
    }

    /**
     * The plain text of inline content, cut to a number of characters: the length a plain field of UJO takes.
     */
    public function cut(Inline $inline, int $length): string
    {
        return mb_substr($this->plain($inline), 0, $length);
    }

    /**
     * Some HTML, each image mark replaced by what `$image` makes of the address, alternative text, attributes and
     * caption of the image.
     *
     * @param callable(array{string, string, string, string}): string $image
     */
    public function images(string $html, callable $image): string
    {
        $pieces = explode("\0", $html);

        for ($index = 1; $index < \count($pieces); $index += 2) {
            $pieces[$index] = $image($this->context->images[$pieces[$index]]);
        }

        return implode('', $pieces);
    }

    private function token(Cursor $cursor): Delimiter|string|null
    {
        foreach ($this->rules[$cursor->current()] as $rule) {
            $token = $rule->lex($cursor, $this);

            if (null !== $token) {
                return $token;
            }
        }

        return null;
    }

    /**
     * A new line: a line break when the text before it ends with two spaces or more, a soft break otherwise; the
     * spaces before it are left out.
     *
     * @param list<Delimiter|string> $nodes
     */
    private function newLine(array &$nodes): void
    {
        $last = array_pop($nodes) ?? '';

        if ($last instanceof Delimiter) {
            $nodes[] = $last;
            $nodes[] = "\n";

            return;
        }

        $trimmed = rtrim($last, ' ');
        $nodes[] = $trimmed.(\strlen($last) - \strlen($trimmed) > 1 ? '<br>' : "\n");
    }

    /**
     * Pairs the delimiter runs: each closer, from the first, with the nearest opener of its character before it —
     * of the same length for the characters other than `*` and `_` —; what is between them becomes the content of
     * the element, and a run not paired is text. As in CommonMark, a search that failed is not made again below the
     * closer it failed for, so that pairing stays linear.
     *
     * The nodes are linked to the ones before and after them, so that an element replaces what it holds without the
     * list being copied. A node keeps its place in the list for the searches to stop at; an element, placed after
     * every node, never stops one, and it holds no delimiter a search could find. A place where a search failed stays
     * valid: pairing only takes delimiters out, so no opener the search could have found appears below it later.
     *
     * @param list<Delimiter|string> $nodes
     */
    private function pair(array $nodes): string
    {
        $count = \count($nodes);

        if (0 === $count) {
            return '';
        }

        $next = range(1, $count);
        $next[$count - 1] = -1;
        $previous = range(-1, $count - 2);
        $head = 0;
        $bottoms = [];

        for ($closer = 0; -1 !== $closer; $closer = $next[$closer]) {
            $close = $nodes[$closer];

            if (!$close instanceof Delimiter || !$close->closes) {
                continue;
            }

            $found = $this->opener($nodes, $previous, $close, $closer, $bottoms[$close->character][$close->length] ?? 0);

            if (null === $found) {
                $bottoms[$close->character][$close->length] = $closer;

                continue;
            }

            [$opener, $open] = $found;
            $length = min(2, $open->length, $close->length);
            $tag = self::TAGS[$close->character][$length];
            $open->length -= $length;
            $close->length -= $length;
            $inner = [];

            for ($node = $next[$opener]; $node !== $closer; $node = $next[$node]) {
                $inner[] = $nodes[$node];
            }

            $element = \count($nodes);
            $nodes[] = '<'.$tag.'>'.$this->literal($inner).'</'.$tag.'>';
            $before = $open->length > 0 ? $opener : $previous[$opener];
            $after = $close->length > 0 ? $closer : $next[$closer];
            $previous[] = $before;
            $next[] = $after;

            if (-1 === $before) {
                $head = $element;
            } else {
                $next[$before] = $element;
            }

            $previous[$after] = $element;

            $closer = $element;
        }

        $kept = [];

        for ($node = $head; -1 !== $node; $node = $next[$node]) {
            $kept[] = $nodes[$node];
        }

        return $this->literal($kept);
    }

    /**
     * @param list<Delimiter|string> $nodes
     * @param array<int, int>        $previous
     *
     * @return array{int, Delimiter}|null
     */
    private function opener(array $nodes, array $previous, Delimiter $close, int $closer, int $bottom): ?array
    {
        for ($index = $previous[$closer]; $index >= $bottom; $index = $previous[$index]) {
            $open = $nodes[$index];

            if ($open instanceof Delimiter && $open->opens && $open->character === $close->character && ('*' === $open->character || '_' === $open->character || $open->length === $close->length)) {
                return [$index, $open];
            }
        }

        return null;
    }

    /**
     * @param list<Delimiter|string> $nodes
     */
    private function literal(array $nodes): string
    {
        return implode('', array_map(static fn (Delimiter|string $node): string => \is_string($node) ? $node : str_repeat($node->character, $node->length), $nodes));
    }
}
