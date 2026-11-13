<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;

/**
 * Gift cards on card stock: the title and the logo at the top, the amount large, the recipient and the message, then
 * the code, the PIN and the date of validity at the foot, the code's image on the right. The words around the values
 * are the application's, in the reader's language.
 */
final readonly class GiftCards
{
    private const float PADDING = 10;

    private LabelSheet $sheet;

    /**
     * @param LabelSheet|null $sheet    the stock; `LabelSheet::letter10()` when none is given
     * @param bool            $cutLines whether a thin grey outline shows where to cut each card
     */
    public function __construct(
        private string $title,
        ?LabelSheet $sheet = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private ?Image $logo = null,
        private string $codeLabel = 'Code',
        private string $pinLabel = 'PIN',
        private string $validUntilLabel = 'Valid until',
        private bool $cutLines = true,
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        $this->sheet = $sheet ?? LabelSheet::letter10();
    }

    /**
     * @param list<GiftCard> $cards
     */
    public function render(array $cards): Document
    {
        return Layout::sheet($this->sheet, $cards, $this->card(...), $this->metadata, $this->conformance);
    }

    private function card(Document $document, GiftCard $card, float $x, float $y): void
    {
        $width = $this->sheet->labelWidth;
        $height = $this->sheet->labelHeight;
        $left = $x + self::PADDING;
        $inner = $width - 2 * self::PADDING;

        if ($this->cutLines) {
            $document->rectangle($x, $y, $width, $height, stroke: Color::gray(200), lineWidth: 0.25);
        }

        $top = $y + $height - self::PADDING;
        $titleLeft = $left;

        if ($this->logo instanceof Image) {
            Layout::image($document, $this->logo, $left, $top - 20, 20, 20);
            $titleLeft += 26;
        }

        Layout::line($document, $titleLeft, $top - 14, $left + $inner - $titleLeft, $this->title, 12, 7, $this->fonts->bold);
        $codeWidth = 0.0;

        if ($card->codeImage instanceof Image) {
            $codeWidth = min($height * 0.4, $inner * 0.3);
            Layout::image($document, $card->codeImage, $left + $inner - $codeWidth, $y + self::PADDING, $codeWidth, $codeWidth);
            $codeWidth += self::PADDING;
        }

        $foot = $y + self::PADDING;
        // The foot, from the bottom up: the date of validity, the PIN, then the code; what is above it stays above.
        $footTop = $foot + 11 + (null === $card->pin ? 0 : 11) + (null === $card->validUntil ? 0 : 10);
        $amountSize = Layout::line($document, $left, $top - 14 - $height * 0.25, $inner, $card->amount, $height * 0.22, 10, $this->fonts->bold);
        $below = $top - 14 - $height * 0.25 - $amountSize * 0.6;

        if (null !== $card->recipient && $below > $footTop) {
            Layout::line($document, $left, $below, $inner, $card->recipient, 9, 6, $this->fonts->regular);
            $below -= 11;
        }

        $messageLines = (int) floor(($below - $footTop) / 9.5) + 1;

        if (null !== $card->message && $below > $footTop) {
            Layout::paragraph($document, $left, $below, $inner - $codeWidth, $card->message, 8, 9.5, min(2, $messageLines), $this->fonts->regular, Color::gray(80));
        }

        if (null !== $card->validUntil) {
            Layout::line($document, $left, $foot, $inner - $codeWidth, $this->validUntilLabel.' '.$card->validUntil, 7, 5, $this->fonts->regular, color: Color::gray(80));
            $foot += 10;
        }

        if (null !== $card->pin) {
            Layout::line($document, $left, $foot, $inner - $codeWidth, $this->pinLabel.' '.$card->pin, 8, 5, $this->fonts->regular);
            $foot += 11;
        }

        Layout::line($document, $left, $foot, $inner - $codeWidth, $this->codeLabel.' '.$card->code, 9, 5, $this->fonts->bold);
    }
}
