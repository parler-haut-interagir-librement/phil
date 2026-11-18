<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Contract\BlockRule;
use Phil\Ujo\Block;

/**
 * The blocks of Markdown, line by line, as UJO blocks whose inline content is not read yet. Each kind of block has
 * its rule, tried in turn on a line, the rules of the dialect last; a container — a quote, a list item, a `{{ }}` block,
 * a `:::` block —
 * gathers its lines, then reads them as a document of its own. A line no rule starts continues the paragraph above,
 * lazily when it is inside a container.
 *
 * @internal
 */
final readonly class BlockParser
{
    /** How deep containers nest; deeper, their markers are text. */
    public const int MAX_DEPTH = 5;

    /** The elements that start an HTML block of CommonMark's sixth kind. */
    private const array HTML_BLOCKS = ['address', 'article', 'aside', 'base', 'basefont', 'blockquote', 'body', 'caption', 'center', 'col', 'colgroup', 'dd', 'details', 'dialog', 'dir', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html', 'iframe', 'legend', 'li', 'link', 'main', 'menu', 'menuitem', 'nav', 'noframes', 'ol', 'optgroup', 'option', 'p', 'param', 'search', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'title', 'tr', 'track', 'ul'];

    /** The elements whose HTML block ends at their closing tag, blank lines included. */
    private const array HTML_VERBATIM = ['script', 'pre', 'style', 'textarea'];

    /** The type of what an HTML block of comments alone gives `parse()`: its text, the note of the next block. */
    private const string COMMENT = '#comment';

    /** The longest note the comments of a block make, in characters. */
    private const int MAX_NOTE = 10_000;

    public function __construct(
        private Context $context,
        private InlineParser $inline,
    ) {
    }

    /**
     * The blocks of lines, each appended in place, so that the time grows with the number of blocks. An HTML block of
     * comments alone is the `note` tune of the block that follows it, or of the last block when none follows.
     *
     * @param list<string> $lines
     *
     * @return list<Block>
     */
    public function parse(array $lines, int $depth = 0): array
    {
        $blocks = [];
        $paragraph = [];
        $notes = [];
        $count = \count($lines);

        for ($at = 0; $at < $count;) {
            $line = $lines[$at];
            $made = [] === $paragraph ? null : $this->continuation($paragraph, $lines, $at);

            if (null !== $made) {
                $paragraph = [];
            } elseif ($this->blank($line)) {
                $made = [[], $at + 1];
            } elseif ($this->isInjection($line) && !$this->blank($lines[$at - 1] ?? '')) {
                $this->paragraph($blocks, $paragraph, $notes);
                $paragraph = [];
                $last = array_pop($blocks);
                $made = [null === $last ? [] : [Layout::apply($last, Attributes::parse(substr(trim($line), 1, -1)), $this->context)], $at + 1];
            } else {
                $made = $this->start($lines, $at, $depth, [] !== $paragraph);
            }

            if (null === $made) {
                $paragraph[] = $line;
                ++$at;

                continue;
            }

            $this->paragraph($blocks, $paragraph, $notes);
            $paragraph = [];
            [$new, $at] = $made;

            foreach ($new as $block) {
                $this->append($blocks, $block, $notes);
            }
        }

        $this->paragraph($blocks, $paragraph, $notes);
        $last = array_key_last($blocks);

        if (null !== $last && [] !== $notes) {
            $blocks[$last] = $this->noted($blocks[$last], $notes);
        }

        return $this->definitions($blocks);
    }

    /**
     * Appends a block, which takes the comments met before it as its note; the text of comments is kept for the next
     * block.
     *
     * @param list<Block>  $blocks
     * @param list<string> $notes
     */
    private function append(array &$blocks, Block $block, array &$notes): void
    {
        if (self::COMMENT === $block->type) {
            $notes[] = \is_string($block->data['text'] ?? null) ? $block->data['text'] : '';

            return;
        }

        $blocks[] = [] === $notes ? $block : $this->noted($block, $notes);
        $notes = [];
    }

    /**
     * A block with the comments as its `note` tune, after the note it has; the comments without text left out.
     *
     * @param non-empty-list<string> $notes
     */
    private function noted(Block $block, array $notes): Block
    {
        $text = implode("\n", array_filter($notes, static fn (string $note): bool => '' !== $note));

        if ('' === $text) {
            return $block;
        }

        $note = $block->tunes['note'] ?? null;
        $earlier = \is_array($note) && \is_string($note['text'] ?? null) ? $note['text']."\n" : '';

        return $block->withTunes([...$block->tunes, 'note' => ['text' => mb_substr($earlier.$text, 0, self::MAX_NOTE)]]);
    }

    /**
     * The inline content a block holds, for a container that holds only inline content: a quote, a list item, a
     * footnote. A block without inline content is left out.
     */
    private static function flatten(Block $block): ?Inline
    {
        return match ($block->type) {
            'paragraph', 'header', 'quote' => Inline::of($block->data['text'] ?? null),
            'code' => Inline::html('<code>'.InlineParser::escape(\is_string($block->data['code'] ?? null) ? $block->data['code'] : '').'</code>'),
            'list' => Inline::join(array_map(static fn (mixed $item): Inline => Inline::of(\is_array($item) ? $item['content'] ?? null : null), self::items($block)), '<br>'),
            default => null,
        };
    }

    /**
     * The items of a list or of a definition list.
     *
     * @return list<mixed>
     */
    public static function items(Block $block): array
    {
        $items = $block->data['items'] ?? null;

        return \is_array($items) ? array_values($items) : [];
    }

    /**
     * The inline content of blocks — and of inline parts — as one text, a separator between two.
     *
     * @param list<Block|Inline|null> $parts
     */
    public static function join(array $parts, string $separator): Inline
    {
        $inlines = array_map(static fn (Block|Inline|null $part): ?Inline => $part instanceof Block ? self::flatten($part) : $part, $parts);

        return Inline::join(array_filter($inlines), $separator);
    }

    /**
     * What a line read after a paragraph makes of it: a Setext underline, or a definition of the paragraph.
     *
     * @param non-empty-list<string> $paragraph
     * @param list<string>           $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function continuation(array $paragraph, array $lines, int $at): ?array
    {
        $line = $lines[$at];
        $text = trim($line);

        return match (true) {
            $this->indent($line) > 3, '' === $text => null,
            '' === trim($text, '=') => [[$this->heading(implode("\n", $paragraph), 1)], $at + 1],
            '' === trim($text, '-') => [[$this->heading(implode("\n", $paragraph), 2)], $at + 1],
            null !== $this->definitionStart($text) => $this->definition($paragraph, $lines, $at),
            default => null,
        };
    }

    /**
     * The block a line starts, and the index of the line after it; `null` when the line is text.
     *
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function start(array $lines, int $at, int $depth, bool $inParagraph): ?array
    {
        if ($this->indent($lines[$at]) > 3) {
            return $inParagraph ? null : $this->indentedCode($lines, $at);
        }

        $rules = [
            fn (): ?array => $this->fence($lines, $at),
            fn (): ?array => $this->atxHeading($lines, $at),
            fn (): ?array => $this->thematicBreak($lines, $at),
            fn (): ?array => $this->quote($lines, $at, $depth),
            fn (): ?array => $this->listing($lines, $at, $depth, $inParagraph),
            fn (): ?array => $this->htmlBlock($lines, $at, $inParagraph),
            fn (): ?array => $this->breaks($lines, $at),
            fn (): ?array => $this->curly($lines, $at, $depth),
            fn (): ?array => $this->colons($lines, $at, $depth),
            fn (): ?array => $this->toc($lines, $at),
            fn (): ?array => $inParagraph ? null : $this->definitionLine($lines, $at, $depth),
            fn (): ?array => $this->table($lines, $at, $depth),
            ...array_map(fn (BlockRule $rule): callable => fn (): ?array => $rule->read($lines, $at, $this->context), $this->context->dialect?->blockRules() ?? []),
        ];

        foreach ($rules as $rule) {
            $made = $rule();

            if (null !== $made) {
                return $made;
            }
        }

        return null;
    }

    /**
     * Whether a line starts a block that ends a paragraph.
     */
    private function interrupts(string $line, int $depth): bool
    {
        return null !== $this->start([$line], 0, $depth, true);
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function fence(array $lines, int $at): ?array
    {
        $indent = $this->indent($lines[$at]);
        $text = substr($lines[$at], $indent);
        $character = match (true) {
            str_starts_with($text, '```') => '`',
            str_starts_with($text, '~~~') => '~',
            default => '',
        };
        $fence = strspn($text, $character);
        $info = trim(substr($text, $fence));

        if ('' === $character || ('`' === $character && str_contains($info, '`'))) {
            return null;
        }

        $code = [];
        $count = \count($lines);
        $next = $at + 1;

        for (; $next < $count; ++$next) {
            $closing = ltrim($lines[$next], ' ');
            $run = strspn($closing, $character);

            if ($this->indent($lines[$next]) < 4 && $run >= $fence && $this->blank(substr($closing, $run))) {
                ++$next;

                break;
            }

            $code[] = substr($lines[$next], min($indent, $this->indent($lines[$next])));
        }

        $language = new Cursor($info)->consumeUntil(" \t{");
        $data = ['code' => implode("\n", $code)];

        return [[$this->context->block('code', 1 === preg_match('/^[A-Za-z0-9_+#.-]{1,32}$/', $language) ? [...$data, 'language' => $language] : $data)], $next];
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}
     */
    private function indentedCode(array $lines, int $at): array
    {
        $code = [];
        $count = \count($lines);
        $next = $at;

        for (; $next < $count && ($this->blank($lines[$next]) || $this->indent($lines[$next]) > 3); ++$next) {
            $code[] = substr($lines[$next], 4);
        }

        return [[$this->context->block('code', ['code' => rtrim(implode("\n", $code), "\n")])], $next];
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function atxHeading(array $lines, int $at): ?array
    {
        $text = rtrim(ltrim($lines[$at], ' '));
        $level = strspn($text, '#');

        if ($level < 1 || $level > 6 || !\in_array($text[$level] ?? ' ', [' ', "\t"], true)) {
            return null;
        }

        $content = substr($text, $level);
        $closed = rtrim($content, '#');

        return [[$this->heading(str_ends_with($closed, ' ') ? $closed : $content, $level)], $at + 1];
    }

    /**
     * A heading, its attributes, `{#id .class}` after a space, read from the end of its text.
     */
    private function heading(string $text, int $level): Block
    {
        $open = strrpos($text, ' {');
        $attributes = false === $open ? '' : substr($text, $open + 1);

        if (false === $open || !$this->isInjection($attributes)) {
            return $this->context->block('header', ['text' => Inline::markdown(trim($text)), 'level' => $level]);
        }

        $heading = $this->context->block('header', ['text' => Inline::markdown(trim(substr($text, 0, $open))), 'level' => $level]);

        return Layout::apply($heading, Attributes::parse(substr($attributes, 1, -1)), $this->context);
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function thematicBreak(array $lines, int $at): ?array
    {
        $compact = str_replace([' ', "\t"], '', $lines[$at]);
        $rule = '' === trim($compact, '*') || '' === trim($compact, '-') || '' === trim($compact, '_');

        return $rule && \strlen($compact) > 2 ? [[$this->context->block('delimiter', [])], $at + 1] : null;
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function quote(array $lines, int $at, int $depth): ?array
    {
        if ($depth >= self::MAX_DEPTH || !str_starts_with(ltrim($lines[$at], ' '), '>')) {
            return null;
        }

        $inner = [];
        $count = \count($lines);
        $next = $at;

        for (; $next < $count; ++$next) {
            $text = ltrim($lines[$next], ' ');

            if ($this->indent($lines[$next]) < 4 && str_starts_with($text, '>')) {
                $text = substr($text, 1);
                $inner[] = str_starts_with($text, ' ') ? substr($text, 1) : $text;
            } elseif ($this->blank($lines[$next]) || $this->blank($inner[\count($inner) - 1]) || $this->interrupts($lines[$next], $depth)) {
                break;
            } else {
                $inner[] = $lines[$next];
            }
        }

        return [[$this->context->block('quote', ['text' => self::join($this->parse($inner, $depth + 1), '<br><br>')])], $next];
    }

    /**
     * A list: its items, each the lines indented under its marker, read as a document; the first paragraph of an item
     * is its content, the lists in it its nested items, and its other blocks follow its content. A list whose every
     * item starts with `[ ]` or `[x]` is a checklist.
     *
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function listing(array $lines, int $at, int $depth, bool $inParagraph): ?array
    {
        $first = $depth < self::MAX_DEPTH ? $this->marker($lines[$at]) : null;
        $ordered = null !== $first && str_contains('.)', $first[0]);

        if (null === $first || ($inParagraph && ($this->blank(substr($lines[$at], $first[2])) || ($ordered && 1 !== $first[1])))) {
            return null;
        }

        $items = [];
        $count = \count($lines);
        $next = $at;

        for (; $next < $count;) {
            $marker = $this->marker($lines[$next]);

            if (null === $marker || $marker[0] !== $first[0]) {
                break;
            }

            $item = [substr($lines[$next], $marker[2])];

            for (++$next; $next < $count; ++$next) {
                $line = $lines[$next];

                if ($this->blank($line)) {
                    $item[] = '';
                } elseif ($this->indent($line) >= $marker[2]) {
                    $item[] = substr($line, $marker[2]);
                } elseif (!$this->blank($item[\count($item) - 1]) && null === $this->marker($line) && !$this->interrupts($line, $depth)) {
                    $item[] = $line;
                } else {
                    break;
                }
            }

            $items[] = $item;
        }

        $boxes = array_map(static fn (array $item): ?bool => match (substr($item[0], 0, 4)) {
            '[ ] ' => false,
            '[x] ', '[X] ' => true,
            default => null,
        }, $items);
        $checklist = !\in_array(null, $boxes, true);
        $items = array_map(fn (array $item, ?bool $checked): array => $this->item($checklist ? [substr($item[0], 4), ...\array_slice($item, 1)] : $item, $depth, $checklist ? $checked : null), $items, $boxes);
        $style = $checklist ? 'checklist' : ($ordered ? 'ordered' : 'unordered');

        return [[$this->context->block('list', ['style' => $style, 'meta' => $ordered && 1 !== $first[1] ? ['start' => $first[1]] : [], 'items' => $items])], $next];
    }

    /**
     * @param list<string> $lines
     *
     * @return array{content: Inline, meta: array{checked?: bool}, items: list<mixed>}
     */
    private function item(array $lines, int $depth, ?bool $checked): array
    {
        $content = [];
        $children = [];

        foreach ($this->parse($lines, $depth + 1) as $block) {
            if ('list' === $block->type) {
                array_push($children, ...self::items($block));
            } else {
                $content[] = $block;
            }
        }

        return ['content' => self::join($content, '<br>'), 'meta' => null === $checked ? [] : ['checked' => $checked], 'items' => $children];
    }

    /**
     * The marker of a list item: its kind — the bullet, or the delimiter of a number —, its number, and the width
     * its content is indented by.
     *
     * @return array{string, int, int}|null
     */
    private function marker(string $line): ?array
    {
        $indent = $this->indent($line);
        $text = substr($line, $indent);
        $digits = strspn($text, '0123456789');
        $bullet = '' !== $text && str_contains('-+*', $text[0]);
        $kind = $bullet ? $text[0] : substr($text, $digits, 1);
        $width = $bullet ? 1 : $digits + 1;
        $after = substr($text, $width);
        $spaces = strspn($after, ' ');

        if ((!$bullet && ($digits < 1 || $digits > 9 || !\in_array($kind, ['.', ')'], true))) || ('' !== $after && !\in_array($after[0], [' ', "\t"], true))) {
            return null;
        }

        return [$kind, (int) $text, $indent + $width + ($spaces > 4 || $this->blank($after) ? 1 : $spaces)];
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function htmlBlock(array $lines, int $at, bool $inParagraph): ?array
    {
        $text = strtolower(ltrim($lines[$at], ' '));
        $cursor = new Cursor($text);
        $cursor->consume('<');

        $closing = $cursor->consume('/');
        $name = $cursor->consumeWhile('abcdefghijklmnopqrstuvwxyz0123456789-');
        $after = $cursor->current();
        $tag = ctype_alpha(substr($name, 0, 1)) && str_contains(" \t>/", $after);

        $end = match (true) {
            !str_starts_with($text, '<') => null,
            !$closing && \in_array($name, self::HTML_VERBATIM, true) && str_contains(" \t>", $after) => '</'.$name.'>',
            str_starts_with($text, '<!--') => '-->',
            $tag && \in_array($name, self::HTML_BLOCKS, true) => '',
            $tag && !$inParagraph && str_ends_with(rtrim($text), '>') && 1 === substr_count($text, '<') => '',
            default => null,
        };

        if (null === $end) {
            return null;
        }

        $html = [];
        $count = \count($lines);
        $next = $at;

        for (; $next < $count && ('' !== $end || !$this->blank($lines[$next])); ++$next) {
            $html[] = $lines[$next];

            if ('' !== $end && str_contains(strtolower($lines[$next]), $end)) {
                ++$next;

                break;
            }
        }

        $html = implode("\n", $html);
        $comments = '-->' === $end ? $this->comments($html) : null;

        return match (true) {
            null !== $comments => [[new Block(self::COMMENT, self::COMMENT, ['text' => $comments])], $next],
            !$this->context->rawHtml => [[$this->context->block('paragraph', ['text' => Inline::html(InlineParser::escape($html))])], $next],
            '-->' === $end => [[], $next],
            default => [[$this->context->block('raw', ['html' => $html])], $next],
        };
    }

    /**
     * The text of an HTML block made of comments alone, each without the white space at its ends and on a line of its
     * own; `null` when the block holds anything else.
     */
    private function comments(string $html): ?string
    {
        preg_match_all('/<!--(.*?)-->/s', $html, $matches);

        return '' === trim(str_replace($matches[0], '', $html)) ? implode("\n", array_map(trim(...), $matches[1])) : null;
    }

    /**
     * The page and column breaks, `\page` and `\column`, and the attributes of the next page,
     * `\page {class,property:value}`.
     *
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function breaks(array $lines, int $at): ?array
    {
        $cursor = new Cursor(trim($lines[$at]));

        if ($cursor->consume('\\column')) {
            $cursor->consume('break');

            return $cursor->isEnd() ? [[$this->context->block('columnBreak', [])], $at + 1] : null;
        }

        if (!$cursor->consume('\\page')) {
            return null;
        }

        $cursor->consume('break');
        $cursor->consumeWhile(' ');

        $attributes = $cursor->rest();

        return match (true) {
            '' === $attributes => [[$this->context->block('pageBreak', [])], $at + 1],
            str_starts_with($attributes, '{') && str_ends_with($attributes, '}') => [[$this->context->block('pageBreak', []), Curly::pageStyle(Attributes::parse(substr($attributes, 1, -1)), $this->context)], $at + 1],
            default => null,
        };
    }

    /**
     * A block, `{{tags` on its line up to `}}` on its own, or a span alone on its line that is a block of
     * a page — a page number, a running footer, a watermark.
     *
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function curly(array $lines, int $at, int $depth): ?array
    {
        $text = trim($lines[$at]);

        if ($depth >= self::MAX_DEPTH || !str_starts_with($text, '{{')) {
            return null;
        }

        if (self::opens($text)) {
            [$inner, $next] = $this->gather($lines, $at, static fn (string $line): int => match (true) {
                '}}' === trim($line) => -1,
                self::opens(trim($line)) => 1,
                default => 0,
            });

            return [Curly::block(Attributes::parse(substr($text, 2)), $this->parse($inner, $depth + 1), $this->context, $this->inline), $next];
        }

        $cursor = new Cursor($text);
        $span = Curly::scan($cursor);
        [$tags, $content] = null !== $span && $cursor->isEnd() ? Attributes::split($span) : ['', ''];
        $blocks = Curly::span(Attributes::parse($tags), $content, $this->context, $this->inline);

        return null === $blocks ? null : [$blocks, $at + 1];
    }

    /**
     * Whether a line opens a block: `{{` and tags, without a space or a closing `}}`.
     */
    private static function opens(string $text): bool
    {
        return str_starts_with($text, '{{') && !str_contains($text, ' ') && !str_contains($text, '}}');
    }

    /**
     * A `:::` block, up to the `:::` that closes it: `::: note A title`, `::: {.class #id}`.
     *
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function colons(array $lines, int $at, int $depth): ?array
    {
        $text = trim($lines[$at]);
        $opening = ltrim(ltrim($text, ':'));

        if ($depth >= self::MAX_DEPTH || $this->context->disables(MarkdownSyntax::ColonBlocks) || !str_starts_with($text, ':::') || '' === $opening) {
            return null;
        }

        [$inner, $next] = $this->gather($lines, $at, static fn (string $line): int => str_starts_with(trim($line), ':::') ? ('' === ltrim(trim($line), ':') ? -1 : 1) : 0);
        $braces = str_starts_with($opening, '{') && str_ends_with($opening, '}');
        [$tags, $title] = $braces ? [substr($opening, 1, -1), ''] : Attributes::split($opening);

        return [Curly::block(Attributes::parse($tags), $this->parse($inner, $depth + 1), $this->context, $this->inline, $title), $next];
    }

    /**
     * The lines inside a block that nests, up to the line that closes it: `$depth` says by how much each line opens
     * (1) or closes (-1) a block.
     *
     * @param list<string>          $lines
     * @param callable(string): int $depth
     *
     * @return array{list<string>, int}
     */
    private function gather(array $lines, int $at, callable $depth): array
    {
        $inner = [];
        $count = \count($lines);
        $open = 1;
        $next = $at + 1;

        for (; $next < $count; ++$next) {
            $open += $depth($lines[$next]);

            if (0 === $open) {
                return [$inner, $next + 1];
            }

            $inner[] = $lines[$next];
        }

        return [$inner, $next];
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function toc(array $lines, int $at): ?array
    {
        return '[TOC]' === trim($lines[$at]) ? [[$this->context->block('toc', [])], $at + 1] : null;
    }

    /**
     * A footnote, `[^label]: text`, with the lines indented under it; a definition the dialect reads; a link
     * reference, `[label]: address "title"`. Each defines, and makes no block; none ends a paragraph.
     *
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function definitionLine(array $lines, int $at, int $depth): ?array
    {
        $cursor = new Cursor(ltrim($lines[$at], ' '));
        $note = $cursor->consume('[^');
        $open = $note || $cursor->consume('[');
        $label = $cursor->consumeUntil("[]\n");

        if (!$open || '' === trim($label) || !$cursor->consume(']:')) {
            return null;
        }

        $cursor->consumeWhile(' ');

        return match (true) {
            $note => $this->footnote($label, $cursor->rest(), $lines, $at, $depth),
            $this->context->dialect?->define($label, substr($cursor->text, $cursor->position()), $this->context) ?? false => [[], $at + 1],
            default => $this->reference($label, $cursor, $at),
        };
    }

    /**
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}
     */
    private function footnote(string $label, string $first, array $lines, int $at, int $depth): array
    {
        $note = [$first];
        $count = \count($lines);
        $next = $at + 1;

        for (; $next < $count; ++$next) {
            $line = $lines[$next];

            if ($this->blank($line) || $this->indent($line) > 3) {
                $note[] = substr($line, 4);
            } elseif ($this->blank($note[\count($note) - 1]) || $this->interrupts($line, $depth) || str_starts_with(ltrim($line), '[')) {
                break;
            } else {
                $note[] = $line;
            }
        }

        $this->context->footnotes[$label] ??= self::join($this->parse($note, $depth + 1), '<br>');

        return [[], $next];
    }

    /**
     * @return array{list<Block>, int}|null
     */
    private function reference(string $label, Cursor $cursor, int $at): ?array
    {
        $address = $cursor->consume('<') ? $cursor->consumeThrough('>') : $cursor->consumeUntil(" \t");
        $cursor->consumeWhile(' ');
        $title = $cursor->rest();
        $quoted = '' === $title || (\in_array(substr($title, 0, 1).substr($title, -1), ['""', "''", '()'], true) && \strlen($title) > 1);

        if (null === $address || '' === $address || !$quoted) {
            return null;
        }

        $key = Context::label($label);

        if (!isset($this->context->references[$key])) {
            $this->context->references[$key] = InlineParser::destination($address);
            $this->context->titles[$key] = InlineParser::title(substr($title, 1, -1));
        }

        return [[], $at + 1];
    }

    /**
     * A table of GitHub: a header row, a delimiter row of as many cells, and the rows up to a blank line, a block or
     * the attributes of the table.
     *
     * @param list<string> $lines
     *
     * @return array{list<Block>, int}|null
     */
    private function table(array $lines, int $at, int $depth): ?array
    {
        $header = $this->cells($lines[$at]);
        $width = \count($header);

        if (!str_contains($lines[$at], '|') || !$this->delimiterRow($lines[$at + 1] ?? '', $width)) {
            return null;
        }

        $rows = [$header];
        $count = \count($lines);
        $next = $at + 2;

        for (; $next < $count && !$this->blank($lines[$next]) && !$this->isInjection($lines[$next]) && !$this->interrupts($lines[$next], $depth); ++$next) {
            $rows[] = \array_slice(array_pad($this->cells($lines[$next]), $width, ''), 0, $width);
        }

        $content = array_map(static fn (array $row): array => array_map(Inline::markdown(...), $row), $rows);

        return [[$this->context->block('table', ['withHeadings' => true, 'content' => $content])], $next];
    }

    /**
     * The cells of a row of a table, split on the pipes that are not escaped.
     *
     * @return list<string>
     */
    private function cells(string $line): array
    {
        $line = trim($line);
        $cursor = new Cursor($line);
        $cursor->consume('|');

        $cells = [];
        $cell = '';

        while (!$cursor->isEnd()) {
            $cell .= $cursor->consumeUntil('|\\');

            if ($cursor->consume('|')) {
                $cells[] = $cell;
                $cell = '';
            } else {
                $cell .= $cursor->consume('\\|') ? '|' : $cursor->advance();
            }
        }

        return array_map(trim(...), '' === $cell && [] !== $cells ? $cells : [...$cells, $cell]);
    }

    private function delimiterRow(string $line, int $width): bool
    {
        $cells = $this->cells($line);

        foreach ($cells as $cell) {
            $dashes = trim($cell, ':');

            if ('' === $dashes || '' !== trim($dashes, '-')) {
                return false;
            }
        }

        return \count($cells) === $width;
    }

    /**
     * A definition list whose term is the paragraph above: each line `: definition`, or a definition the dialect
     * starts, starts a definition, and the lines under it continue it.
     *
     * @param non-empty-list<string> $term
     * @param list<string>           $lines
     *
     * @return array{list<Block>, int}
     */
    private function definition(array $term, array $lines, int $at): array
    {
        $definitions = [];
        $count = \count($lines);
        $next = $at;

        for (; $next < $count && !$this->blank($lines[$next]); ++$next) {
            $text = trim($lines[$next]);

            $start = $this->definitionStart($text);

            if (null !== $start) {
                $definitions[] = $start;
            } else {
                $definitions[\count($definitions) - 1] .= "\n".$text;
            }
        }

        $item = ['term' => Inline::markdown(implode("\n", array_map(trim(...), $term))), 'definitions' => array_map(Inline::markdown(...), $definitions)];

        return [[$this->context->block('definitionList', ['items' => [$item]])], $next];
    }

    /**
     * The text of the definition a line starts under a term: after `: `, or after the mark of the dialect.
     */
    private function definitionStart(string $text): ?string
    {
        return str_starts_with($text, ': ') ? ltrim(substr($text, 2)) : $this->context->dialect?->definition($text);
    }

    /**
     * The definition lists that follow each other, as one list.
     *
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private function definitions(array $blocks): array
    {
        $merged = [];
        /** @var array<int, list<mixed>> $items the items of each list that others joined, by its index in `$merged` */
        $items = [];
        $open = null;

        foreach ($blocks as $block) {
            if ('definitionList' !== $block->type) {
                $open = null;
                $merged[] = $block;
            } elseif (null === $open) {
                $open = \count($merged);
                $merged[] = $block;
            } else {
                $items[$open] ??= self::items($merged[$open]);
                array_push($items[$open], ...self::items($block));
            }
        }

        $joined = [];

        foreach ($merged as $index => $block) {
            $joined[] = isset($items[$index]) ? $block->withData(['items' => $items[$index]]) : $block;
        }

        return $joined;
    }

    /**
     * The lines of a paragraph as its block, after the blocks before it; the dialect takes the mark it starts with, and
     * gives it its tunes.
     *
     * @param list<Block>  $blocks
     * @param list<string> $lines
     * @param list<string> $notes
     */
    private function paragraph(array &$blocks, array $lines, array &$notes): void
    {
        if ([] === $lines) {
            return;
        }

        $text = rtrim(implode("\n", array_map(static fn (string $line): string => ltrim($line, " \t"), $lines)));
        [$text, $tunes] = $this->context->dialect?->paragraph($text) ?? [$text, []];
        $this->append($blocks, $this->context->block('paragraph', ['text' => Inline::markdown(ltrim($text, ' '))], $tunes), $notes);
    }

    private function blank(string $line): bool
    {
        return '' === trim($line);
    }

    private function indent(string $line): int
    {
        return strspn($line, ' ');
    }

    /**
     * Whether a line is attributes in braces, which apply to the block right above it.
     */
    private function isInjection(string $line): bool
    {
        $text = trim($line);
        $first = ltrim(substr($text, 1), ':')[0] ?? '';

        return str_starts_with($text, '{') && str_ends_with($text, '}') && 1 === substr_count($text, '{') && (ctype_alpha($first) || '.' === $first || '#' === $first);
    }
}
