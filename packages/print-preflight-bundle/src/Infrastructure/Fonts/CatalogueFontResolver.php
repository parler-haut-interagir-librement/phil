<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Infrastructure\Fonts;

use Override;
use Phil\Fonts\FontStyle;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\PrintPreflight\UsedFont;
use Phil\PrintPreflightBundle\Application\Port\FontResolver;

/**
 * The fonts of a measured document found in the catalogue of phil/fonts-bundle: the face of the family, weight and
 * style, with the embedding permissions its file declares. A face the catalogue does not hold throws `FontNotFound`.
 */
final readonly class CatalogueFontResolver implements FontResolver
{
    public function __construct(
        private FontCatalogue $catalogue,
    ) {
    }

    #[Override]
    public function resolve(string $family, int $weight, FontStyle $style, bool $embedded, bool $subset): UsedFont
    {
        return UsedFont::fromFont($this->catalogue->face($family, $weight, $style), $embedded, $subset);
    }
}
