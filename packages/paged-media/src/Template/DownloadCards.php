<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;

/**
 * Download cards, one per code, on card stock: the title and its subtitle, how to redeem the code and where, the code
 * itself in a box, and the image to scan on the right. The address is shown as given, as the reader types it.
 */
final readonly class DownloadCards
{
    private const float PADDING = 10;

    private LabelSheet $sheet;

    /**
     * @param string          $instructions how to redeem a code, such as "Enter this code at"
     * @param string          $address      where to redeem it, as the reader types it
     * @param LabelSheet|null $sheet        the stock; `LabelSheet::letter10()` when none is given
     */
    public function __construct(
        private string $instructions,
        private string $address,
        ?LabelSheet $sheet = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private ?Image $logo = null,
        private bool $cutLines = true,
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        $this->sheet = $sheet ?? LabelSheet::letter10();
    }

    /**
     * @param list<DownloadCard> $cards
     */
    public function render(array $cards): Document
    {
        return Layout::sheet($this->sheet, $cards, $this->card(...), $this->metadata, $this->conformance);
    }

    private function card(Document $document, DownloadCard $card, float $x, float $y): void
    {
        $width = $this->sheet->labelWidth;
        $height = $this->sheet->labelHeight;
        $left = $x + self::PADDING;
        $inner = $width - 2 * self::PADDING;

        if ($this->cutLines) {
            $document->rectangle($x, $y, $width, $height, stroke: Color::gray(200), lineWidth: 0.25);
        }

        if ($card->codeImage instanceof Image) {
            $side = min($height - 2 * self::PADDING, $inner * 0.35);
            Layout::image($document, $card->codeImage, $left + $inner - $side, $y + ($height - $side) / 2, $side, $side);
            $inner -= $side + self::PADDING;
        }

        $top = $y + $height - self::PADDING;
        $titleLeft = $left;

        if ($this->logo instanceof Image) {
            Layout::image($document, $this->logo, $left, $top - 20, 20, 20);
            $titleLeft += 26;
        }

        Layout::line($document, $titleLeft, $top - 14, $left + $inner - $titleLeft, $card->title, 12, 7, $this->fonts->bold);

        if (null !== $card->subtitle) {
            Layout::line($document, $titleLeft, $top - 26, $left + $inner - $titleLeft, $card->subtitle, 8, 6, $this->fonts->regular, color: Color::gray(80));
        }

        $below = Layout::paragraph($document, $left, $y + $height * 0.52, $inner, $this->instructions, 8, 9.5, 2, $this->fonts->regular);
        Layout::line($document, $left, $below, $inner, $this->address, 9, 6, $this->fonts->bold);
        $box = min(26, $height * 0.22);
        $document->rectangle($left, $y + self::PADDING, $inner, $box, Color::gray(240), Color::gray(120), 0.5);
        Layout::line($document, $left + 4, $y + self::PADDING + $box * 0.3, $inner - 8, $card->code, $box * 0.5, 6, $this->fonts->bold, true);
    }
}
