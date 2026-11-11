<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\BBCode;

/**
 * A tag of a BBCode source, read: its name in lower case, its option — what follows `=` —, its attributes and flags,
 * the tag as written, and what it holds: text, and the tags opened inside it.
 *
 * @internal
 */
final class Node
{
    /** The tags that hold the content of a block, or stand for one. */
    private const array BLOCKS = [
        'quote', 'list', '*', 'table', 'tr', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'center', 'left', 'right',
        'justify', 'align', 'note', 'descriptive', 'warning', 'alert', 'wide', 'block', 'columns', 'column', 'statblock',
        'monster', 'credit', 'artist', 'classtable', 'dl', 'dt', 'dd', 'footer', 'watermark', 'banner', 'code', 'html',
        'hr', 'pagebreak', 'columnbreak', 'toc', 'spacer', 'pagenumber', 'decoration',
    ];

    /** @var list<self|string> */
    public array $children = [];

    /** Whether a block stands among the tags it holds, a footnote's content left out. */
    public bool $holdsBlock = false;

    /**
     * @param array<string, string> $attributes
     * @param list<string>          $flags
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $option = null,
        public readonly array $attributes = [],
        public readonly array $flags = [],
        public readonly string $written = '',
    ) {
    }

    /**
     * Adds a child: text is joined to the text before it.
     */
    public function add(self|string $child): void
    {
        $last = array_key_last($this->children);

        if (\is_string($child) && null !== $last && \is_string($this->children[$last])) {
            $this->children[$last] .= $child;

            return;
        }

        $this->children[] = $child;
    }

    /**
     * Whether the tag is a block: a structure, a page's mark, code, or a spoiler that has a title or holds blocks.
     */
    public function isBlock(): bool
    {
        return \in_array($this->name, self::BLOCKS, true) || ('spoiler' === $this->name && (null !== $this->option || $this->holdsBlock));
    }

    /**
     * The text it holds, the tags inside left out: the address of a link, the content of a tag read as written.
     */
    public function text(): string
    {
        return implode('', array_filter($this->children, \is_string(...)));
    }

    public function attribute(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }
}
