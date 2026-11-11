<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\BBCode;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\FrontMatter;

/**
 * The first pass of a BBCode reading: the source lexed by the shared cursor into a tree of tags, in one pass and in
 * linear time, however the tags are nested or left open.
 *
 * A tag is `[name]`, `[name=option]`, `[name="option" key=value flag]` or `[name key=value flag]`, its name in any
 * case. A tag this reader does not know stays as written. A tag left open is closed at the end; a closing tag closes
 * the nearest open tag of its name and the tags opened inside it, or stays as written when none is open — the reader
 * keeps, for each name, the positions its tags are open at, so that it never searches the stack. An item, a row, a
 * cell, a column, a term or a definition closes the one of its kind left open in the same parent, and stays as written
 * outside a parent. Tags nested deeper than 32 stay as written. The content of `[code]`, `[icode]`, `[img]`, `[html]`
 * and `[var]` is kept as written up to their closing tag; `[metadata]` and `[style]` are the metadata of the source,
 * `[var=name]` defines a variable.
 *
 * @internal
 */
final class Parser
{
    public const int MAX_DEPTH = 32;

    private const array KNOWN = [
        'b', 'i', 'u', 's', 'sup', 'sub', 'mark', 'highlight', 'url', 'email', 'color', 'size', 'font', 'spoiler',
        'footnote', 'code', 'icode', 'img', 'html', 'var', 'metadata', 'style', 'hr', 'pagebreak', 'columnbreak', 'toc',
        'spacer', 'pagenumber', 'decoration', 'nbsp', 'icon', 'hspace', 'quote', 'list', '*', 'table', 'tr', 'td', 'th',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'center', 'left', 'right', 'justify', 'align', 'note', 'descriptive',
        'warning', 'alert', 'wide', 'block', 'columns', 'column', 'statblock', 'monster', 'credit', 'artist',
        'classtable', 'dl', 'dt', 'dd', 'footer', 'watermark', 'banner', 'ruby',
    ];

    /** The tags whose content is kept as written, up to their closing tag. */
    private const array VERBATIM = ['code', 'icode', 'img', 'html', 'var', 'metadata', 'style'];

    /** The tags that hold nothing and are never closed. */
    private const array SINGLE = ['hr', 'pagebreak', 'columnbreak', 'toc', 'spacer', 'pagenumber', 'decoration', 'nbsp', 'icon', 'hspace'];

    /** The tags that close the one of their kind left open in their parent: the kinds, and the parent. */
    private const array SIBLINGS = [
        '*' => [['*'], 'list'],
        'tr' => [['tr'], 'table'],
        'td' => [['td', 'th'], 'tr'],
        'th' => [['td', 'th'], 'tr'],
        'column' => [['column'], 'columns'],
        'dt' => [['dt', 'dd'], 'dl'],
        'dd' => [['dt', 'dd'], 'dl'],
    ];

    private const string NAME = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789*';

    /** @var array<string, string> the value of each variable, by name */
    public array $variables = [];

    /** @var array<array-key, mixed> */
    public array $metadata = [];

    private readonly Node $root;

    /** @var list<Node> the tags open, the outermost first */
    private array $stack = [];

    /** @var array<string, list<int>> the positions in the stack of the open tags of each name */
    private array $open = [];

    public function __construct()
    {
        $this->root = new Node('');
    }

    public function parse(string $source): Node
    {
        $cursor = new Cursor($source);

        while (!$cursor->isEnd()) {
            $text = $cursor->consumeUntil('[');
            $tag = '' === $text ? $this->tag($cursor) : null;

            match (true) {
                '' !== $text => $this->top()->add($text),
                null === $tag => $this->top()->add($cursor->advance()),
                $tag[0] => $this->close($tag[1], $tag[3]),
                default => $this->open($tag[1], $tag[2], $tag[3], $cursor),
            };
        }

        return $this->root;
    }

    private function top(): Node
    {
        return $this->stack[\count($this->stack) - 1] ?? $this->root;
    }

    /**
     * The tag at the cursor — whether it closes, its name, what follows the name, the tag as written —, consumed;
     * `null`, and nothing consumed, when the text there is not a tag this reader knows.
     *
     * @return array{bool, string, string, string}|null
     */
    private function tag(Cursor $cursor): ?array
    {
        $start = $cursor->position();
        $cursor->advance();
        $closing = $cursor->consume('/');
        $name = strtolower($cursor->consumeWhile(self::NAME));
        $rest = $closing ? '' : $cursor->consumeUntil("[]\n");

        if (!\in_array($name, self::KNOWN, true) || !$cursor->consume(']') || ('' !== $rest && '=' !== $rest[0] && ' ' !== $rest[0])) {
            $cursor->moveTo($start);

            return null;
        }

        return [$closing, $name, $rest, substr($cursor->text, $start, $cursor->position() - $start)];
    }

    private function open(string $name, string $rest, string $written, Cursor $cursor): void
    {
        [$option, $attributes, $flags] = Options::parse($rest);

        if (\in_array($name, self::VERBATIM, true)) {
            $this->verbatim(new Node($name, $option, $attributes, $flags, $written), $this->through($cursor, $name));

            return;
        }

        if (\in_array($name, self::SINGLE, true)) {
            $this->leaf(new Node($name, $option, $attributes, $flags, $written));

            return;
        }

        if (!$this->siblings($name) || \count($this->stack) >= self::MAX_DEPTH) {
            $this->top()->add($written);

            return;
        }

        $node = new Node($name, $option, $attributes, $flags, $written);
        $this->leaf($node);
        $this->open[$name][] = \count($this->stack);
        $this->stack[] = $node;
    }

    /**
     * For an item, a row, a cell, a column, a term or a definition: whether its parent is open, the one of its kind
     * left open in that parent closed. Any other tag may open anywhere.
     */
    private function siblings(string $name): bool
    {
        if (!isset(self::SIBLINGS[$name])) {
            return true;
        }

        [$kinds, $parent] = self::SIBLINGS[$name];
        $within = $this->last($parent);

        if (null === $within) {
            return false;
        }

        foreach ($kinds as $kind) {
            $at = $this->last($kind);

            if (null !== $at && $at > $within) {
                $this->closeTo($at);
            }
        }

        return true;
    }

    private function close(string $name, string $written): void
    {
        $at = $this->last($name);

        if (null === $at) {
            $this->top()->add($written);
        } else {
            $this->closeTo($at);
        }
    }

    /**
     * Closes the tags open at a position of the stack and above it.
     */
    private function closeTo(int $position): void
    {
        foreach (array_splice($this->stack, $position) as $node) {
            array_pop($this->open[$node->name]);
        }
    }

    /**
     * The position in the stack of the nearest open tag of a name, `null` when none is open.
     */
    private function last(string $name): ?int
    {
        $positions = $this->open[$name] ?? [];

        return [] === $positions ? null : $positions[\count($positions) - 1];
    }

    /**
     * Adds a tag to the one open; a block marks the tags open around it as holding a block, up to a footnote, whose
     * content is folded apart, or up to a tag marked already.
     */
    private function leaf(Node $node): void
    {
        $this->top()->add($node);

        for ($at = \count($this->stack) - 1; $node->isBlock() && $at >= 0 && !$this->stack[$at]->holdsBlock; --$at) {
            $this->stack[$at]->holdsBlock = true;

            if ('footnote' === $this->stack[$at]->name) {
                return;
            }
        }
    }

    /**
     * A tag whose content is kept as written: a variable defined, the metadata read, or a tag holding its text.
     */
    private function verbatim(Node $node, string $content): void
    {
        if ('var' === $node->name && null !== $node->option) {
            $this->variables[$node->option] = $content;
        } elseif ('metadata' === $node->name) {
            $this->metadata = [...$this->metadata, ...FrontMatter::yaml($content) ?? []];
        } elseif ('style' === $node->name) {
            $this->metadata['style'] = $content;
        } else {
            $node->add($content);
            $this->leaf($node);
        }
    }

    /**
     * The text up to the closing tag of a name, in any case, consumed with it; the rest of the source when it never
     * comes.
     */
    private function through(Cursor $cursor, string $name): string
    {
        $closing = '[/'.$name.']';
        $start = $cursor->position();
        $at = stripos($cursor->text, $closing, $start);

        if (false === $at) {
            return $cursor->rest();
        }

        $cursor->moveTo($at + \strlen($closing));

        return substr($cursor->text, $start, $at - $start);
    }
}
