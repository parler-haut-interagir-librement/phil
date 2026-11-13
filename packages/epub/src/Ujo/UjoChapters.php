<?php

declare(strict_types=1);

namespace Phil\Epub\Ujo;

use Phil\Epub\Chapter;
use Phil\Epub\HtmlSplitter;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\InvalidJson;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;

/**
 * The chapters of a UJO (Universal JSON Output) document, rendered by `phil/ujo` as purified HTML: the document
 * is checked, rendered whole — so that its table of contents and footnotes see every heading —, then split at its
 * top-level headings of level `$splitLevel` or higher. The start of the document is page `$firstPage`, and each
 * `pageBreak` block starts the next page: each becomes a page break marker, which the book lists in its page list, and
 * a marker that would end a chapter opens the next one instead. With `$firstPage` null, page breaks leave nothing.
 */
final readonly class UjoChapters
{
    private Ujo $ujo;

    public function __construct(
        ?Normalizer $normalizer = null,
        ?HtmlRenderer $renderer = null,
        private int $splitLevel = 1,
        private ?int $firstPage = 1,
    ) {
        $this->ujo = new Ujo($normalizer, ($renderer ?? HtmlRenderer::default())->with('pageBreak', new PageMarker()));
    }

    /**
     * @return list<Chapter>
     *
     * @throws InvalidJson
     * @throws InvalidDocument
     * @throws UnknownBlockType
     */
    public function chapters(Document|string $document): array
    {
        $parts = HtmlSplitter::split($this->ujo->toHtml($document), $this->splitLevel);
        $last = \count($parts) - 1;

        for ($index = 0; $index < $last; ++$index) {
            while (str_ends_with(rtrim($parts[$index]), PageMarker::PLACEHOLDER)) {
                $parts[$index] = substr(rtrim($parts[$index]), 0, -\strlen(PageMarker::PLACEHOLDER));
                $parts[$index + 1] = PageMarker::PLACEHOLDER.$parts[$index + 1];
            }
        }

        $parts = array_values(array_filter($parts, static fn (string $part): bool => '' !== $part));
        $page = $this->firstPage;
        $chapters = [];

        foreach ($parts as $index => $part) {
            $html = 0 === $index && null !== $page ? $this->marker($page) : '';

            foreach (explode(PageMarker::PLACEHOLDER, $part) as $position => $piece) {
                if (0 !== $position && null !== $page) {
                    $html .= $this->marker(++$page);
                }

                $html .= $piece;
            }

            $chapters[] = new Chapter($html);
        }

        return $chapters;
    }

    private function marker(int $page): string
    {
        return '<span id="page-'.$page.'" role="doc-pagebreak" epub:type="pagebreak" aria-label="'.$page.'"></span>';
    }
}
