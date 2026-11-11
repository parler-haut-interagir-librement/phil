<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Exception\InvalidDocument;

/**
 * The colour space an image or an element is defined in.
 */
enum ColourSpace: string
{
    case Gray = 'gray';
    case Rgb = 'rgb';
    case Cmyk = 'cmyk';

    /** A named ink printed on its own plate: a Pantone colour, a varnish, a metallic. */
    case Spot = 'spot';

    /**
     * The colour space of a PDF colour space name, with or without its slash: `DeviceRGB`, `CalRGB`, `DeviceCMYK`,
     * `DeviceGray`, `CalGray`, `Separation`, `DeviceN`.
     */
    public static function fromPdf(string $name): self
    {
        return match (ltrim($name, '/')) {
            'DeviceGray', 'CalGray' => self::Gray,
            'DeviceRGB', 'CalRGB' => self::Rgb,
            'DeviceCMYK' => self::Cmyk,
            'Separation', 'DeviceN' => self::Spot,
            default => throw InvalidDocument::because(\sprintf('The PDF colour space "%s" is not one the preflight knows.', $name)),
        };
    }
}
