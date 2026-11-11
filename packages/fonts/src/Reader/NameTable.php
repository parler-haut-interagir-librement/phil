<?php

declare(strict_types=1);

namespace Phil\Fonts\Reader;

use Phil\Fonts\Name\NameRecord;
use Phil\Fonts\Name\Names;

/**
 * Reads the strings of a `name` table: UTF-16 for the Unicode and Windows platforms, Mac Roman for the Macintosh one;
 * the strings of other encodings are left out.
 *
 * @internal
 */
final readonly class NameTable
{
    /** The characters of Mac Roman from 0x80 to 0xFF, in order. */
    private const string MAC_ROMAN = 'ÄÅÇÉÑÖÜáàâäãåçéèêëíìîïñóòôöõúùûü†°¢£§•¶ß®©™´¨≠ÆØ∞±≤≥¥µ∂∑∏π∫ªºΩæø'
        ."¿¡¬√ƒ≈∆«»…\u{00A0}ÀÃÕŒœ–—“”‘’÷◊ÿŸ⁄€‹›ﬁﬂ‡·‚„‰ÂÊÁËÈÍÎÏÌÓÔ\u{F8FF}ÒÚÛÙıˆ˜¯˘˙˚¸˝˛ˇ";

    public static function read(Binary $table): Names
    {
        $storage = $table->uint16(4);
        $records = [];

        for ($index = 0, $count = $table->uint16(2); $index < $count; ++$index) {
            $at = 6 + 12 * $index;
            $platform = $table->uint16($at);
            $encoding = $table->uint16($at + 2);
            $bytes = $table->slice($storage + $table->uint16($at + 10), $table->uint16($at + 8));
            $value = match (true) {
                0 === $platform, 3 === $platform && \in_array($encoding, [0, 1, 10], true) => mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE'),
                1 === $platform && 0 === $encoding => self::macRoman($bytes),
                default => null,
            };

            if (null !== $value) {
                $records[] = new NameRecord($platform, $encoding, $table->uint16($at + 4), $table->uint16($at + 6), $value);
            }
        }

        return new Names($records);
    }

    private static function macRoman(string $bytes): string
    {
        $characters = mb_str_split(self::MAC_ROMAN);

        return implode('', array_map(
            static fn (string $byte): string => \ord($byte[0]) < 0x80 ? $byte : $characters[\ord($byte[0]) - 0x80],
            str_split($bytes),
        ));
    }
}
