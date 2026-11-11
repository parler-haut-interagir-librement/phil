<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Override;
use Phil\Markup\Reader\Markdown\Contract\MarkdownDialect;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\RawNormalizer;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Document;
use Phil\Ujo\Normalizer;

/**
 * Markdown read into a UJO document by this library's own analyser: CommonMark, the GitHub extensions — tables,
 * strikethrough, autolinks, task lists —, footnotes, attributes, definition lists, heading anchors, a table of
 * contents, front matter, `:::` and `{{ }}` blocks, pages and columns. Raw HTML is kept, as `raw` blocks and inline
 * elements for the sanitizer, or escaped as text. A `MarkdownDialect` adds its syntax to it, or reads some differently.
 *
 * Any text is a document: the reader never throws. The document is normalized by `phil/ujo` before it is returned.
 */
final readonly class MarkdownReader implements MarkupReader
{
    public function __construct(
        private bool $rawHtml = false,
        private ?Normalizer $normalizer = null,
        private ?MarkdownDialect $dialect = null,
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        $source = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{FFFD}"], $source);
        $lines = array_map($this->tabs(...), explode("\n", $source));
        [$metadata, $lines] = $this->dialect?->frontMatter($lines) ?? FrontMatter::split($lines);
        $context = new Context($this->rawHtml, $this->dialect);
        $inline = new InlineParser($context);
        $blocks = new Resolver($context, $inline)->document(new BlockParser($context, $inline)->parse($lines));
        $normalizer = $this->normalizer ?? RawNormalizer::get();

        return new Reading($normalizer->normalize(new Document($blocks)), $metadata);
    }

    /**
     * A line whose indentation holds tabs, each a run of spaces to the next multiple of four.
     */
    private function tabs(string $line): string
    {
        $indent = strspn($line, " \t");
        $column = 0;

        foreach (str_split(substr($line, 0, $indent)) as $character) {
            $column += "\t" === $character ? 4 - $column % 4 : 1;
        }

        return str_repeat(' ', $column).substr($line, $indent);
    }
}
