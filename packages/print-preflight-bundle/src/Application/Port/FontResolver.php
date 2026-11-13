<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application\Port;

use Phil\Fonts\FontStyle;
use Phil\PrintPreflight\UsedFont;

/**
 * The font file behind a family, weight and style a browser or a template names, read for its embedding permissions.
 * With phil/fonts-bundle, the catalogue of the font directory answers.
 */
interface FontResolver
{
    public function resolve(string $family, int $weight, FontStyle $style, bool $embedded, bool $subset): UsedFont;
}
