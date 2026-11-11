<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

/**
 * The rules of the built-in checks: the code of their findings and the severity they have unless the profile says
 * otherwise.
 */
enum Rule: string
{
    case PageCountEmpty = 'page-count.empty';
    case PageCountBelowMinimum = 'page-count.below-minimum';
    case PageCountAboveMaximum = 'page-count.above-maximum';
    case PageCountMultiple = 'page-count.multiple';
    case PageSizeNotAccepted = 'page-size.not-accepted';
    case PageSizeMixed = 'page-size.mixed';
    case BleedTooSmall = 'bleed.too-small';
    case FontNotEmbedded = 'font.not-embedded';
    case FontRestricted = 'font.restricted';
    case FontBitmapOnly = 'font.bitmap-only';
    case FontSubsetForbidden = 'font.subset-forbidden';
    case ImageBelowMinimum = 'image.below-minimum';
    case ImageBelowTarget = 'image.below-target';
    case ImageAboveMaximum = 'image.above-maximum';
    case ColourRgbInCmyk = 'colour.rgb-in-cmyk';
    case ColourCmykInRgb = 'colour.cmyk-in-rgb';
    case ColourSpot = 'colour.spot';
    case PlacementOutsideTrim = 'placement.outside-trim';
    case PlacementCrossesTrim = 'placement.crosses-trim';
    case PlacementInSafetyMargin = 'placement.in-safety-margin';
    case PlacementShortOfBleed = 'placement.short-of-bleed';
    case InkImageAboveMaximum = 'ink.image-above-maximum';
    case InkFlatAboveMaximum = 'ink.flat-above-maximum';
    case OverprintWhite = 'overprint.white';
    case OverprintNotAllowed = 'overprint.not-allowed';
    case LineHairline = 'line.hairline';
    case TransparencyNotAllowed = 'transparency.not-allowed';

    public function severity(): Severity
    {
        return match ($this) {
            self::ImageBelowTarget, self::ColourSpot, self::PlacementOutsideTrim, self::PlacementInSafetyMargin,
            self::OverprintNotAllowed, self::LineHairline, self::TransparencyNotAllowed => Severity::Warning,
            self::ImageAboveMaximum, self::ColourRgbInCmyk, self::ColourCmykInRgb => Severity::Notice,
            default => Severity::Error,
        };
    }
}
