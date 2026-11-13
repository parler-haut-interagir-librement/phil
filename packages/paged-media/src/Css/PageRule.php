<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Css;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Pdf\Number;
use Phil\PagedMedia\Pdf\PageSize;

/**
 * The `@page` rule a paged-media engine lays a document out with: the trimmed size of the page, its margins, the bleed
 * around it and the printer's marks drawn in the bleed.
 *
 * It writes CSS Paged Media, which any engine reads; it lays nothing out itself. Every measure is in points, so a
 * {@see PageSize} of the native writer and a page an engine prints have the same size.
 */
final readonly class PageRule
{
    /** The widest bleed accepted: one inch, well over the 3 mm or ⅛ inch a printer asks for. */
    public const float MAXIMUM_BLEED = 72.0;

    /** @var list<float> */
    private array $margins;

    /**
     * @param PageSize     $size    the page once trimmed
     * @param array<float> $margins one to four margins, in the order of the CSS `margin` shorthand; the engine's
     *                              default with none
     * @param float        $bleed   how far the background runs past the trimmed page, up to {@see self::MAXIMUM_BLEED}
     * @param PrinterMarks $marks   the marks drawn in the bleed
     */
    public function __construct(
        public PageSize $size,
        array $margins = [],
        public float $bleed = 0.0,
        public PrinterMarks $marks = PrinterMarks::None,
    ) {
        if (\count($margins) > 4 || !array_is_list($margins)) {
            throw InvalidDocument::because('A page has one to four margins, as the CSS margin shorthand writes them.');
        }

        $limit = min($size->width, $size->height) / 2;

        foreach ($margins as $margin) {
            if ($margin < 0 || $margin >= $limit) {
                throw InvalidDocument::because(\sprintf('A margin is at least 0 and under half the shorter side, %s points; %s is not.', Number::format($limit), $margin));
            }
        }

        if ($bleed < 0 || $bleed > self::MAXIMUM_BLEED) {
            throw InvalidDocument::because(\sprintf('The bleed is between 0 and %s points; %s is not.', Number::format(self::MAXIMUM_BLEED), $bleed));
        }

        // An integer given as a margin is kept as the float it stands for.
        $this->margins = array_map(static fn (float $margin): float => $margin, $margins);
    }

    /**
     * @return list<float>
     */
    public function margins(): array
    {
        return $this->margins;
    }

    /**
     * The rule, as a stylesheet of its own, to place before the document's: the same rule gives the same text.
     */
    public function css(): string
    {
        $declarations = [\sprintf('size: %s %s', $this->points($this->size->width), $this->points($this->size->height))];

        if ([] !== $this->margins) {
            $declarations[] = 'margin: '.implode(' ', array_map($this->points(...), $this->margins));
        }

        if ($this->bleed > 0) {
            $declarations[] = 'bleed: '.$this->points($this->bleed);
        }

        if (PrinterMarks::None !== $this->marks) {
            $declarations[] = 'marks: '.$this->marks->value;
        }

        return '@page { '.implode('; ', $declarations).' }';
    }

    private function points(float $value): string
    {
        $number = Number::format($value);

        return '0' === $number ? '0' : $number.'pt';
    }
}
