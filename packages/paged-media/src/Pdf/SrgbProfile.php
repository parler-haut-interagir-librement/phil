<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

/**
 * An ICC version 2 display profile for sRGB (IEC 61966-2-1), written from the colorimetry the standard publishes: the
 * D50-adapted primaries, the D50 white point, and the sRGB transfer curve sampled at 1 024 points. It is the output
 * intent of a PDF/A document.
 *
 * @internal
 */
final class SrgbProfile
{
    public const string DESCRIPTION = 'sRGB IEC61966-2.1 (phil/paged-media)';

    /** The D50-adapted XYZ values of the red, green and blue primaries, and of the D50 white point. */
    private const array RED = [0.4360, 0.2225, 0.0139];

    private const array GREEN = [0.3851, 0.7169, 0.0971];

    private const array BLUE = [0.1431, 0.0606, 0.7141];

    private const array WHITE = [0.9642, 1.0, 0.8249];

    private const int CURVE_POINTS = 1024;

    public static function bytes(): string
    {
        $curve = self::curve();
        $tags = [
            'desc' => self::description(),
            'cprt' => 'text'."\0\0\0\0".'No copyright, use freely'."\0",
            'wtpt' => self::xyz(self::WHITE),
            'rXYZ' => self::xyz(self::RED),
            'gXYZ' => self::xyz(self::GREEN),
            'bXYZ' => self::xyz(self::BLUE),
            'rTRC' => $curve,
            'gTRC' => $curve,
            'bTRC' => $curve,
        ];

        $offset = 128 + 4 + 12 * \count($tags);
        $table = Bytes::uint32(\count($tags));
        $data = '';
        $written = [];

        // A tag whose data another tag already holds points at it: the three curves are written once.
        foreach ($tags as $signature => $tag) {
            if (!isset($written[$tag])) {
                $written[$tag] = $offset + \strlen($data);
                $data .= $tag.str_repeat("\0", (4 - \strlen($tag) % 4) % 4);
            }

            $table .= $signature.Bytes::uint32($written[$tag]).Bytes::uint32(\strlen($tag));
        }

        $size = 128 + \strlen($table) + \strlen($data);
        $header = Bytes::uint32($size)
            ."\0\0\0\0"
            ."\x02\x10\0\0"
            .'mntrRGB XYZ '
            .pack('n6', 2026, 1, 1, 0, 0, 0)
            .'acsp'
            // No platform, flags, device, attributes, and the perceptual rendering intent.
            .str_repeat("\0", 28)
            .self::xyzValues(self::WHITE)
            .str_repeat("\0", 48);

        return $header.$table.$data;
    }

    /**
     * @param list<float> $values
     */
    private static function xyz(array $values): string
    {
        return 'XYZ '."\0\0\0\0".self::xyzValues($values);
    }

    /**
     * Each value as an s15Fixed16Number.
     *
     * @param list<float> $values
     */
    private static function xyzValues(array $values): string
    {
        return implode('', array_map(static fn (float $value): string => Bytes::uint32((int) round($value * 65536)), $values));
    }

    private static function curve(): string
    {
        $points = '';

        for ($index = 0; $index < self::CURVE_POINTS; ++$index) {
            $encoded = $index / (self::CURVE_POINTS - 1);
            $linear = $encoded <= 0.04045 ? $encoded / 12.92 : (($encoded + 0.055) / 1.055) ** 2.4;
            $points .= Bytes::uint16((int) round($linear * 65535));
        }

        return 'curv'."\0\0\0\0".Bytes::uint32(self::CURVE_POINTS).$points;
    }

    /**
     * A version 2 text description: the ASCII name, and empty Unicode and ScriptCode names.
     */
    private static function description(): string
    {
        $name = self::DESCRIPTION."\0";

        // After the ASCII name: no Unicode name (language and count), no ScriptCode name (code, count and 67 bytes).
        return 'desc'."\0\0\0\0".Bytes::uint32(\strlen($name)).$name.str_repeat("\0", 78);
    }
}
