<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;

/**
 * A large-print edition of a text: 18 points or more, left aligned and never justified, a line and a half apart, wide
 * margins, black on white, headings in bold kept with what follows them, and the page number at the foot of each page.
 */
final readonly class LargePrint
{
    /** The smallest size large print is set in, in points. */
    public const float SMALLEST = 18;

    private PageSize $page;

    /**
     * @param float $size        the paragraphs' size, 18 points or more
     * @param float $headingSize the headings' size, at least the paragraphs'
     */
    public function __construct(
        ?PageSize $page = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private float $size = self::SMALLEST,
        private float $headingSize = 24,
        private float $margin = 72,
        private bool $pageNumbers = true,
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        $this->page = $page ?? PageSize::letter();

        if ($size < self::SMALLEST || $headingSize < $size) {
            throw InvalidDocument::because(\sprintf('Large print is set in %d points or more, its headings no smaller; %s and %s are not.', self::SMALLEST, $size, $headingSize));
        }

        if ($margin < 18 || 2 * $margin + 4 * $headingSize > min($this->page->width, $this->page->height)) {
            throw InvalidDocument::because(\sprintf('The margins of a large-print page are 18 points or more and leave room for text; %s does not.', $margin));
        }
    }

    /**
     * @param list<LargePrintBlock> $blocks
     */
    public function render(array $blocks): Document
    {
        $document = new Document($this->page, $this->metadata, conformance: $this->conformance);
        $width = $this->page->width - 2 * $this->margin;
        $y = $this->newPage($document);

        foreach ($blocks as $block) {
            $size = $block->heading ? $this->headingSize : $this->size;
            $font = $block->heading ? $this->fonts->bold : $this->fonts->regular;
            $leading = $size * 1.5;
            $lines = Document::wrap($block->text, $width, $size, $font);
            // A heading keeps two lines of text under it on its page.
            $needed = $block->heading ? \count($lines) * $leading + 2 * $this->size * 1.5 : $leading;

            if ($y - $needed < $this->margin && $y < $this->top()) {
                $y = $this->newPage($document);
            }

            foreach ($lines as $line) {
                if ($y - $size < $this->margin - $size * 0.5) {
                    $y = $this->newPage($document);
                }

                $document->text($this->margin, $y - $size, $line, $size, $font);
                $y -= $leading;
            }

            $y -= $this->size * 0.75;
        }

        return $document;
    }

    private function top(): float
    {
        return $this->page->height - $this->margin;
    }

    private function newPage(Document $document): float
    {
        $document->addPage();

        if ($this->pageNumbers) {
            $number = (string) $document->pageCount();
            $document->text(($this->page->width - $this->fonts->regular->width($number, $this->size)) / 2, $this->margin / 2, $number, $this->size, $this->fonts->regular);
        }

        return $this->top();
    }
}
