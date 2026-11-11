<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Html;

use Dom\HTMLDocument;
use Dom\HTMLElement;
use Override;
use Phil\Markup\Contract\ExtractedElementStore;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\RawNormalizer;
use Phil\Markup\Reader\Reading;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Document;
use Phil\Ujo\Html\LanguageTag;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\EmbedServices;

/**
 * HTML read into a UJO document: what a block editor renders — the HTML of `phil/ujo`, its `ujo-*` classes read back
 * into the blocks they came from —, what a paste brings — the styles of a word processor read as bold, italic,
 * underline and strikethrough —, and the HTML of older texts: paragraphs, headings, lists, quotes, figures, code,
 * rules, tables with their caption, spans and header cells, definition lists, details, alignment, and the inline
 * elements UJO keeps — abbreviations, passages in another language, ruby, formulas in MathML. A frame of a service the
 * `embed` block knows is one, a video and a sound are their blocks, a download link alone in its paragraph is an
 * attached file, and an inline SVG drawing, purified by `phil/svg-sanitizer`, is stored by the store of extracted
 * elements and shown as an image. The language of a block is its `language` tune, its `data-*` attributes its `data`
 * tune and the comments it holds its `note` tune. Scripts, styles and forms are left out, and the elements UJO has no
 * place for keep their text. The title, the language and the named `<meta>` of a whole page are its metadata.
 *
 * What the reader does not keep — an element dropped, a frame of an unknown service, a link of a refused scheme or to
 * an anchor the page lacks, a drawing without a store — is listed in the reading's losses.
 *
 * The source is parsed by the HTML5 parser of PHP, as a browser parses it, so that any text is a document: the reader
 * never throws. The document is normalized by `phil/ujo` before it is returned.
 */
final readonly class HtmlReader implements MarkupReader
{
    public function __construct(
        private ?Normalizer $normalizer = null,
        private EmbedServices $services = new EmbedServices(),
        private ?ExtractedElementStore $store = null,
        private SvgSanitizer $sanitizer = new SvgSanitizer(),
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        $document = HTMLDocument::createFromString(str_replace("\0", "\u{FFFD}", $source), \LIBXML_NOERROR, 'UTF-8');
        $context = new Context(false);
        $inline = new InlineParser($context);
        $body = $document->body;
        $blocks = $body instanceof HTMLElement ? new Walker($context, $document, $this->services, $this->store, $this->sanitizer)->blocks($body, 0) : [];
        $normalizer = $this->normalizer ?? RawNormalizer::get();

        return new Reading($normalizer->normalize(new Document(new Resolver($context, $inline)->document($blocks))), $this->metadata($document), $context->losses);
    }

    /**
     * The title of the page, its language, `<html lang>`, and its `<meta name="…" content="…">`.
     *
     * @return array<string, string>
     */
    private function metadata(HTMLDocument $document): array
    {
        $metadata = '' === $document->title ? [] : ['title' => $document->title];
        $language = $document->documentElement?->getAttribute('lang') ?? '';

        if (LanguageTag::isValid($language)) {
            $metadata['language'] = $language;
        }

        foreach ($document->head->children ?? [] as $element) {
            $name = strtolower($element->getAttribute('name') ?? '');

            if ('meta' === $element->localName && '' !== $name) {
                $metadata[$name] = $element->getAttribute('content') ?? '';
            }
        }

        return $metadata;
    }
}
