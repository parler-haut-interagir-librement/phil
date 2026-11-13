<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;

/**
 * Address labels on sheets: each address centred in its label, its first line in bold, each line made smaller when it
 * is too wide, and as many lines as the label holds.
 */
final readonly class AddressLabels
{
    private const float PADDING = 8;

    private LabelSheet $sheet;

    /**
     * @param LabelSheet|null $sheet the sheet; `LabelSheet::letter30()` when none is given
     * @param float           $size  the size of the lines, made smaller down to 6 points to fit
     */
    public function __construct(
        ?LabelSheet $sheet = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private float $size = 10,
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        $this->sheet = $sheet ?? LabelSheet::letter30();
    }

    /**
     * @param list<AddressLabel> $labels
     */
    public function render(array $labels): Document
    {
        return Layout::sheet($this->sheet, $labels, $this->label(...), $this->metadata, $this->conformance);
    }

    private function label(Document $document, AddressLabel $label, float $x, float $y): void
    {
        $leading = $this->size * 1.2;
        $lines = \array_slice($label->lines, 0, max(1, (int) floor(($this->sheet->labelHeight - 2 * self::PADDING) / $leading)));
        $baseline = $y + ($this->sheet->labelHeight + \count($lines) * $leading) / 2 - $this->size;

        foreach ($lines as $index => $line) {
            Layout::line($document, $x + self::PADDING, $baseline, $this->sheet->labelWidth - 2 * self::PADDING, $line, $this->size, 6, 0 === $index ? $this->fonts->bold : $this->fonts->regular);
            $baseline -= $leading;
        }
    }
}
