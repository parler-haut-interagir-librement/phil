<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Creole;

use Override;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\Markup\Reader\Wiki\Pipeline;
use Phil\Markup\Reader\Wiki\WikiLinks;
use Phil\Ujo\Normalizer;

/**
 * Creole 1.0, with its additions, read into a UJO document by this library's own analyser: headings, bold, italic,
 * links, images, lists, tables, `{{{nowiki}}}` and preformatted text, line breaks, the escape `~` and rules;
 * monospace, superscript, subscript, underline, terms, definitions, indents and quotes; and the extensions in its own
 * style, placeholders `<<footnote>>…<</footnote>>`, `<<toc>>`, `<<note title="…">>…<</note>>`, that give it every
 * feature of the Markdown reader. Raw HTML, `<<html>>`, is kept as a `raw` block or escaped as text.
 *
 * Any text is a document, read in linear time: the reader never throws. The document is normalized by `phil/ujo`
 * before it is returned.
 */
final readonly class CreoleReader implements MarkupReader
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
        return Pipeline::read(new CreoleSyntax(), $source, $this->rawHtml, $this->normalizer, $this->links);
    }
}
