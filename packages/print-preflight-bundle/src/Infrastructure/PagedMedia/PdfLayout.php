<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Infrastructure\PagedMedia;

use Phil\Fonts\EmbeddingPermissions;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document as Pdf;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflight\UsedFont;

/**
 * Draws on a document of the native PDF writer of phil/paged-media and records what it places, for the preflight.
 *
 * Each page is added with the bleed its media box carries beyond the trim box on every side; coordinates are those of
 * the writer — points from the bottom left corner of the media box. An image is recorded with its pixel size and colour
 * space, a rectangle and a line of text as boxes, and the font of each line of text as a used font: a TrueType font as
 * embedded, subset when its licence allows it; a standard font as not embedded.
 */
final class PdfLayout
{
    /** @var list<array{PageSize, Length, list<PlacedImage>, list<Box>}> */
    private array $pages = [];

    /** @var array<string, UsedFont> by name */
    private array $fonts = [];

    public function __construct(
        private readonly Pdf $pdf,
    ) {
    }

    public function pdf(): Pdf
    {
        return $this->pdf;
    }

    /**
     * Adds a page of the media box size given, which carries the bleed on every side; none by default.
     */
    public function addPage(PageSize $size, ?Length $bleed = null): void
    {
        $this->pdf->addPage($size);
        $this->pages[] = [$size, $bleed ?? Length::pt(0), [], []];
    }

    public function image(string $id, Image $image, float $x, float $y, float $width, float $height, bool $bleeds = false): void
    {
        $this->pdf->image($image, $x, $y, $width, $height);
        $index = $this->current();
        $this->pages[$index][2][] = new PlacedImage($id, $this->frame($index, $x, $y, $width, $height), $image->width, $image->height, ColourSpace::fromPdf($image->colorSpace), $bleeds);
    }

    public function rectangle(string $id, float $x, float $y, float $width, float $height, ?Color $fill = null, ?Color $stroke = null, bool $bleeds = false): void
    {
        $this->pdf->rectangle($x, $y, $width, $height, $fill, $stroke);
        $index = $this->current();
        $this->pages[$index][3][] = new Box($id, $this->frame($index, $x, $y, $width, $height), $this->colourSpace($fill ?? $stroke ?? Color::black()), $bleeds);
    }

    /**
     * Writes a line of text with its baseline starting at (x, y); its box is as wide as the text measures and as tall as
     * the font size, from a quarter of the size below the baseline.
     */
    public function text(string $id, float $x, float $y, string $text, float $size = 10, StandardFont|TrueTypeFont $font = StandardFont::Helvetica, ?Color $color = null): void
    {
        $this->pdf->text($x, $y, $text, $size, $font, $color);
        $index = $this->current();
        $this->pages[$index][3][] = new Box($id, $this->frame($index, $x, $y - $size / 4, $font->width($text, $size), $size), $this->colourSpace($color ?? Color::black()));
        $used = $font instanceof TrueTypeFont
            ? new UsedFont($font->postScriptName(), new EmbeddingPermissions($font->allowsSubsetting() ? 0 : 0x0100), true, $font->allowsSubsetting())
            : new UsedFont($font->value, embedded: false);
        $this->fonts[$used->name] = $used;
    }

    /**
     * What was placed, for `Preflighter::check()`.
     */
    public function document(): Document
    {
        return new Document(
            array_map(static fn (array $page): Page => new Page(
                new Size(Length::pt($page[0]->width - 2 * $page[1]->points), Length::pt($page[0]->height - 2 * $page[1]->points)),
                $page[1],
                $page[2],
                $page[3],
            ), $this->pages),
            array_values($this->fonts),
        );
    }

    /**
     * The page drawn on: the writer has already refused a drawing before the first page.
     */
    private function current(): int
    {
        return \count($this->pages) - 1;
    }

    private function frame(int $page, float $x, float $y, float $width, float $height): Rectangle
    {
        return Rectangle::fromPdf($x, $y, $width, $height, $this->pages[$page][0]->height, $this->pages[$page][1]);
    }

    private function colourSpace(Color $color): ColourSpace
    {
        return str_ends_with($color->operator(false), ' g') ? ColourSpace::Gray : ColourSpace::Rgb;
    }
}
