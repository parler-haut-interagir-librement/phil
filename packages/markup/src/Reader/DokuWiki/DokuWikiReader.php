<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\DokuWiki;

use Override;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\Markup\Reader\Wiki\Pipeline;
use Phil\Markup\Reader\Wiki\WikiLinks;
use Phil\Ujo\Normalizer;

/**
 * The syntax of DokuWiki read into a UJO document by this library's own analyser: headings, emphasis, monospace,
 * links to pages, to other sites and to e-mail addresses, media placed by their spaces, footnotes `((…))`, lists,
 * tables, quotes, indented code, `<code>`, `<file>`, `<nowiki>` and `%%…%%`, line breaks and rules; and the extensions
 * in its own style — macros `~~TOC~~` and `~~PAGEBREAK~~`, definitions `  = Term : Definition`, the tags of its
 * plugins such as `<note>`, `<WRAP>`, `<columns>`, `<hidden>` — that give it every feature of the Markdown reader.
 * Raw HTML, `<html>`, is kept as a `raw` block or escaped as text.
 *
 * Any text is a document, read in linear time: the reader never throws. The document is normalized by `phil/ujo`
 * before it is returned.
 */
final readonly class DokuWikiReader implements MarkupReader
{
    public function __construct(
        private bool $rawHtml = false,
        private ?Normalizer $normalizer = null,
        private WikiLinks $links = new WikiLinks(),
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        return Pipeline::read(new DokuWikiSyntax(), $source, $this->rawHtml, $this->normalizer, $this->links);
    }
}
