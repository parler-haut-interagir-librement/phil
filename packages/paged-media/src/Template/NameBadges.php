<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;

/**
 * The name badges of an event, on sheets of badge inserts: a band at the top with the event's name and its logo, the
 * name as large as the badge allows, then a subtitle and an organisation.
 */
final readonly class NameBadges
{
    private const float PADDING = 12;

    private LabelSheet $sheet;

    /**
     * @param string          $event  the event's name, in the band
     * @param Color|null      $accent the band's colour; grey when none is given
     * @param Image|null      $logo   drawn at the left of the band
     * @param LabelSheet|null $sheet  the sheet of inserts; `LabelSheet::letter6()` when none is given
     */
    public function __construct(
        private string $event,
        ?LabelSheet $sheet = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private ?Color $accent = null,
        private ?Image $logo = null,
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        $this->sheet = $sheet ?? LabelSheet::letter6();
    }

    /**
     * @param list<Badge> $badges
     */
    public function render(array $badges): Document
    {
        return Layout::sheet($this->sheet, $badges, $this->badge(...), $this->metadata, $this->conformance);
    }

    private function badge(Document $document, Badge $badge, float $x, float $y): void
    {
        $width = $this->sheet->labelWidth;
        $height = $this->sheet->labelHeight;
        $inner = $width - 2 * self::PADDING;
        $band = $height * 0.2;
        $document->rectangle($x, $y + $height - $band, $width, $band, $this->accent ?? Color::gray(64));
        $textX = $x + self::PADDING;

        if ($this->logo instanceof Image) {
            Layout::image($document, $this->logo, $textX, $y + $height - $band + 4, $band - 8, $band - 8);
            $textX += $band;
        }

        Layout::line($document, $textX, $y + $height - $band / 2 - 4, $x + $width - self::PADDING - $textX, $this->event, 12, 7, $this->fonts->bold, color: Color::gray(255));
        $nameSize = Layout::line($document, $x + self::PADDING, $y + $height * 0.45, $inner, $badge->name, $height * 0.2, 12, $this->fonts->bold, true);

        if (null !== $badge->subtitle) {
            Layout::line($document, $x + self::PADDING, $y + $height * 0.45 - $nameSize * 0.3 - 14, $inner, $badge->subtitle, 12, 8, $this->fonts->regular, true, Color::gray(80));
        }

        if (null !== $badge->organisation) {
            Layout::line($document, $x + self::PADDING, $y + self::PADDING, $inner, $badge->organisation, 11, 7, $this->fonts->regular, true);
        }
    }
}
