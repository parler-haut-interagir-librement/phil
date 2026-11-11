<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\BBCode;

use Override;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\RawNormalizer;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Document;
use Phil\Ujo\Normalizer;

/**
 * BBCode read into a UJO document by this library's own analyser: the tags of forums — bold, italic, links, images,
 * quotes with their author, code with its language, lists, tables, headings, colors, alignment, spoilers — and the
 * extensions in the style of BBCode that give it every feature of the Markdown reader: footnotes, definition lists,
 * a table of contents, attributes, notes and boxes, columns, breaks, stat blocks and the other blocks of printed pages,
 * metadata and raw HTML, kept as `raw` blocks or escaped as text.
 *
 * Any text is a document, read in linear time: the reader never throws. The document is normalized by `phil/ujo`
 * before it is returned.
 */
final readonly class BBCodeReader implements MarkupReader
{
    public function __construct(
        private bool $rawHtml = false,
        private ?Normalizer $normalizer = null,
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        $parser = new Parser();
        $root = $parser->parse(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{FFFD}"], $source));
        $context = new Context($this->rawHtml);
        $inline = new InlineParser($context);
        $blocks = new Resolver($context, $inline)->document(new Builder($context, $inline, $parser->variables)->blocks($root, 0));
        $normalizer = $this->normalizer ?? RawNormalizer::get();

        return new Reading($normalizer->normalize(new Document($blocks)), $parser->metadata);
    }
}
