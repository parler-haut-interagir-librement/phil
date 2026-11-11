<?php

declare(strict_types=1);

namespace Phil\Fonts\Reader;

use Phil\Fonts\Axis;
use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\Exception\InvalidFont;
use Phil\Fonts\Font;
use Phil\Fonts\FontStyle;
use Phil\Fonts\Outlines;

/**
 * Builds the metadata of a face from its tables: `name`, `OS/2`, `head`, `post`, `fvar` and `cmap`. Only `name` is
 * required; each other table missing leaves its values at their defaults.
 *
 * @internal
 */
final readonly class FaceReader
{
    /**
     * @param array<string, Binary> $tables
     */
    public static function read(array $tables): Font
    {
        $name = $tables['name'] ?? throw InvalidFont::because('The font has no "name" table.');
        [$weight, $widthClass, $style, $fsType] = self::os2($tables['OS/2'] ?? null, $tables['head'] ?? null);
        [$italicAngle, $fixedPitch] = self::post($tables['post'] ?? null);

        return new Font(
            names: NameTable::read($name),
            weight: $weight,
            widthClass: $widthClass,
            style: $style,
            embedding: new EmbeddingPermissions($fsType),
            outlines: match (true) {
                isset($tables['glyf']) => Outlines::TrueType,
                isset($tables['CFF ']) || isset($tables['CFF2']) => Outlines::PostScript,
                default => Outlines::None,
            },
            axes: isset($tables['fvar']) ? self::axes($tables['fvar']) : [],
            unicodeRanges: isset($tables['cmap']) ? CharacterMap::ranges($tables['cmap']) : [],
            italicAngle: $italicAngle,
            fixedPitch: $fixedPitch,
        );
    }

    /**
     * The weight, width class, style and embedding bits; without `OS/2`, the bold and italic bits of `head`.
     *
     * @return array{int<1, 1000>, int<1, 9>, FontStyle, int}
     */
    private static function os2(?Binary $os2, ?Binary $head): array
    {
        if (!$os2 instanceof Binary) {
            $macStyle = $head?->uint16(44) ?? 0;

            return [0 !== ($macStyle & 1) ? 700 : 400, 5, 0 !== ($macStyle & 2) ? FontStyle::Italic : FontStyle::Normal, 0];
        }

        $weight = $os2->uint16(4);
        $width = $os2->uint16(6);
        $selection = $os2->length() >= 64 ? $os2->uint16(62) : 0;

        return [
            $weight >= 1 && $weight <= 1000 ? $weight : 400,
            $width >= 1 && $width <= 9 ? $width : 5,
            match (true) {
                0 !== ($selection & 0x0200) => FontStyle::Oblique,
                0 !== ($selection & 0x0001) => FontStyle::Italic,
                default => FontStyle::Normal,
            },
            $os2->uint16(8),
        ];
    }

    /**
     * @return array{float, bool}
     */
    private static function post(?Binary $post): array
    {
        return $post instanceof Binary ? [$post->fixed(4), 0 !== $post->uint32(12)] : [0.0, false];
    }

    /**
     * @return list<Axis>
     */
    private static function axes(Binary $fvar): array
    {
        $axes = [];
        $first = $fvar->uint16(4);
        $size = $fvar->uint16(10);

        for ($index = 0, $count = $fvar->uint16(8); $index < $count; ++$index) {
            $at = $first + $size * $index;
            $axes[] = new Axis($fvar->tag($at), $fvar->fixed($at + 4), $fvar->fixed($at + 8), $fvar->fixed($at + 12), $fvar->uint16($at + 18));
        }

        return $axes;
    }
}
