<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;

/**
 * Product labels on sheets: the name on two lines at most, the detail under it, the price in bold at the foot with the
 * SKU, and the code's image on the right when there is one.
 */
final readonly class ProductLabels
{
    private const float PADDING = 6;

    private LabelSheet $sheet;

    /**
     * @param LabelSheet|null $sheet the sheet; `LabelSheet::letter30()` when none is given
     */
    public function __construct(
        ?LabelSheet $sheet = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        $this->sheet = $sheet ?? LabelSheet::letter30();
    }

    /**
     * @param list<ProductLabel> $labels
     */
    public function render(array $labels): Document
    {
        return Layout::sheet($this->sheet, $labels, $this->label(...), $this->metadata, $this->conformance);
    }

    private function label(Document $document, ProductLabel $label, float $x, float $y): void
    {
        $height = $this->sheet->labelHeight;
        $width = $this->sheet->labelWidth - 2 * self::PADDING;

        if ($label->code instanceof Image) {
            $side = $height - 2 * self::PADDING;
            Layout::image($document, $label->code, $x + $this->sheet->labelWidth - self::PADDING - $side, $y + self::PADDING, $side, $side);
            $width -= $side + self::PADDING;
        }

        $size = min(10, $height / 6);
        $below = Layout::paragraph($document, $x + self::PADDING, $y + $height - self::PADDING - $size, $width, $label->name, $size, $size * 1.15, 2, $this->fonts->bold);

        if (null !== $label->detail) {
            Layout::line($document, $x + self::PADDING, $below, $width, $label->detail, $size * 0.8, 5, $this->fonts->regular, color: Color::gray(80));
        }

        $priceSize = Layout::line($document, $x + self::PADDING, $y + self::PADDING, $width * 0.6, $label->price, min(14, $height / 4), 6, $this->fonts->bold);

        if (null !== $label->sku) {
            $skuSize = Layout::fittedSize($label->sku, $width * 0.4, $size * 0.7, 5, $this->fonts->regular);
            $document->textRight($x + self::PADDING + $width, $y + self::PADDING + ($priceSize - $skuSize) / 4, $label->sku, $skuSize, $this->fonts->regular);
        }
    }
}
