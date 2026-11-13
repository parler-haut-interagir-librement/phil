<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Infrastructure\PagedMedia;

use Phil\Fonts\FontStyle;
use Phil\Fonts\Format;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\PagedMedia\Font\TrueTypeFont;

/**
 * The TrueType fonts of the catalogue for the native PDF writer of phil/paged-media, by family, weight and style. The
 * writer embeds TrueType outlines only, so the face is looked for among the `.ttf` files; it refuses a font whose
 * licence forbids embedding it, and embeds whole a font that forbids subsetting.
 */
final readonly class PdfFonts
{
    public function __construct(
        private FontCatalogue $catalogue,
    ) {
    }

    public function get(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal): TrueTypeFont
    {
        return TrueTypeFont::fromFile($this->catalogue->require($family, $weight, $style, Format::TrueType)->absolutePath);
    }
}
