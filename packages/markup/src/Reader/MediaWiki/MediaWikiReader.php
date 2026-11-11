<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\MediaWiki;

use Override;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\Markup\Reader\Wiki\Pipeline;
use Phil\Markup\Reader\Wiki\WikiLinks;
use Phil\Ujo\Normalizer;

/**
 * The wikitext of MediaWiki read into a UJO document by this library's own analyser: headings, emphasis, links to
 * pages and to other sites, files, lists, terms and definitions, indents, tables, preformatted text, `<nowiki>`,
 * `<pre>`, `<syntaxhighlight>`, rules, entities and magic words; and the extensions in its own style — `<ref>` and
 * `{{Footnote|…}}`, `{{TOC}}`, `{{Page break}}`, tags such as `<note title="…">`, `<columns>`, `<statblock>` — that
 * give it every feature of the Markdown reader. Raw HTML, `<html>`, is kept as a `raw` block or escaped as text.
 *
 * Any text is a document, read in linear time: the reader never throws. The document is normalized by `phil/ujo`
 * before it is returned.
 */
final readonly class MediaWikiReader implements MarkupReader
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
        return Pipeline::read(new MediaWikiSyntax(), $source, $this->rawHtml, $this->normalizer, $this->links);
    }
}
