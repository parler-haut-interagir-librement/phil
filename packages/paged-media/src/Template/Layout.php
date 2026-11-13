<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Closure;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;

/**
 * What the printed templates share: labels laid out on sheets, page after page, and text fitted to a width.
 *
 * @internal
 */
final class Layout
{
    /** The ellipsis that ends a text cut to fit. */
    private const string ELLIPSIS = '…';

    /**
     * A document of the sheet's pages, each item drawn in its label, in reading order, a new page when one is full.
     *
     * @template T
     *
     * @param list<T>                                  $items
     * @param Closure(Document, T, float, float): void $draw  draws an item from its label's bottom left corner
     */
    public static function sheet(LabelSheet $sheet, array $items, Closure $draw, Metadata $metadata, ?Conformance $conformance): Document
    {
        $document = new Document($sheet->page, $metadata, conformance: $conformance);

        foreach ($items as $index => $item) {
            $place = $index % $sheet->perPage();

            if (0 === $place) {
                $document->addPage();
            }

            [$x, $y] = $sheet->corner($place);
            $draw($document, $item, $x, $y);
        }

        return $document;
    }

    /**
     * Writes one line of text in the width, at the size given or smaller down to the smallest, then cut with an
     * ellipsis; centred or from the left. Returns the size written at.
     */
    public static function line(Document $document, float $x, float $y, float $width, string $text, float $size, float $smallest, StandardFont|TrueTypeFont $font, bool $centred = false, ?Color $color = null): float
    {
        if ('' === $text) {
            return $size;
        }

        $fitted = self::fittedSize($text, $width, $size, $smallest, $font);
        $text = self::cut($text, $width, $fitted, $font);
        $document->text($centred ? $x + ($width - $font->width($text, $fitted)) / 2 : $x, $y, $text, $fitted, $font, $color);

        return $fitted;
    }

    /**
     * Writes text wrapped to the width, from a first baseline down, as many lines as the count allows, the last cut
     * with an ellipsis when text remains. Returns the baseline under the last line written.
     */
    public static function paragraph(Document $document, float $x, float $y, float $width, string $text, float $size, float $leading, int $lines, StandardFont|TrueTypeFont $font, ?Color $color = null): float
    {
        $wrapped = Document::wrap($text, $width, $size, $font);

        if (\count($wrapped) > $lines) {
            $kept = \array_slice($wrapped, 0, $lines);
            $kept[$lines - 1] = self::cut($kept[$lines - 1].' '.$wrapped[$lines], $width, $size, $font, true);
            $wrapped = $kept;
        }

        foreach ($wrapped as $line) {
            if ('' !== $line) {
                $document->text($x, $y, $line, $size, $font, $color);
            }

            $y -= $leading;
        }

        return $y;
    }

    /**
     * Places an image in a box, as large as it fits with its proportions kept, centred.
     */
    public static function image(Document $document, Image $image, float $x, float $y, float $width, float $height): void
    {
        $scale = min($width / $image->width, $height / $image->height);
        $drawnWidth = $image->width * $scale;
        $drawnHeight = $image->height * $scale;
        $document->image($image, $x + ($width - $drawnWidth) / 2, $y + ($height - $drawnHeight) / 2, $drawnWidth, $drawnHeight);
    }

    /**
     * The largest size from the one given down to the smallest, by half points, at which the text fits the width.
     */
    public static function fittedSize(string $text, float $width, float $size, float $smallest, StandardFont|TrueTypeFont $font): float
    {
        while ($size > $smallest && $font->width($text, $size) > $width) {
            $size = max($smallest, $size - 0.5);
        }

        return $size;
    }

    /**
     * The text cut to the width with an ellipsis, or as it is when it fits and no cut is forced.
     */
    private static function cut(string $text, float $width, float $size, StandardFont|TrueTypeFont $font, bool $forced = false): string
    {
        if (!$forced && $font->width($text, $size) <= $width) {
            return $text;
        }

        $characters = mb_str_split(rtrim($text));

        while ([] !== $characters && $font->width(implode('', $characters).self::ELLIPSIS, $size) > $width) {
            array_pop($characters);
        }

        return rtrim(implode('', $characters)).self::ELLIPSIS;
    }
}
