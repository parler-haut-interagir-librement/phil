<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;

/**
 * Shelf labels on sheets: the name across the top, the price large on the right, the unit price under it, the SKU and
 * the location at the foot, and the code's image at the bottom left when there is one.
 */
final readonly class ShelfLabels
{
    private const float PADDING = 6;

    private LabelSheet $sheet;

    /**
     * @param LabelSheet|null $sheet the sheet; `LabelSheet::letter10()` when none is given
     */
    public function __construct(
        ?LabelSheet $sheet = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        $this->sheet = $sheet ?? LabelSheet::letter10();
    }

    /**
     * @param list<ShelfLabel> $labels
     */
    public function render(array $labels): Document
    {
        return Layout::sheet($this->sheet, $labels, $this->label(...), $this->metadata, $this->conformance);
    }

    private function label(Document $document, ShelfLabel $label, float $x, float $y): void
    {
        $height = $this->sheet->labelHeight;
        $width = $this->sheet->labelWidth - 2 * self::PADDING;
        $left = $x + self::PADDING;
        $nameSize = min(12, $height / 6);
        Layout::line($document, $left, $y + $height - self::PADDING - $nameSize, $width, $label->name, $nameSize, 6, $this->fonts->bold);
        $document->line($left, $y + $height - 2 * self::PADDING - $nameSize, $left + $width, $y + $height - 2 * self::PADDING - $nameSize, 0.5, Color::gray(160));
        $priceSize = Layout::fittedSize($label->price, $width * 0.65, $height * 0.35, 8, $this->fonts->bold);
        $priceBaseline = $y + $height * 0.42;
        $document->textRight($left + $width, $priceBaseline, $label->price, $priceSize, $this->fonts->bold);

        if (null !== $label->unitPrice) {
            $unitSize = Layout::fittedSize($label->unitPrice, $width * 0.65, 8, 5, $this->fonts->regular);
            $document->textRight($left + $width, $priceBaseline - $unitSize * 1.5, $label->unitPrice, $unitSize, $this->fonts->regular);
        }

        if ($label->code instanceof Image) {
            Layout::image($document, $label->code, $left, $y + self::PADDING + 10, $width * 0.3, $height * 0.45);
        }

        $foot = implode(' — ', array_filter([$label->sku, $label->location], static fn (?string $part): bool => null !== $part && '' !== $part));
        Layout::line($document, $left, $y + self::PADDING, $width, $foot, 7, 5, $this->fonts->regular, color: Color::gray(80));
    }
}
