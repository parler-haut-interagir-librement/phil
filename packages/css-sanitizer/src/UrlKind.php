<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

/**
 * What a URL of a style sheet loads: an image — a background, a list marker, a mask, an SVG filter, an option of
 * `image-set()` —, a font of an `@font-face` rule, or the style sheet of an `@import` rule.
 */
enum UrlKind: string
{
    case Image = 'image';
    case Font = 'font';
    case StyleSheet = 'style-sheet';
}
