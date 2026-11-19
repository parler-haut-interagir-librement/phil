<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

/**
 * Bytes of an 8-bit character set read as UTF-8: the Windows code pages `mbstring` knows, Mac Roman, and Windows-1252
 * for any other — the code page of most documents written in Western languages.
 *
 * @internal
 */
final class Codepage
{
    public const string MAC_ROMAN = 'mac';

    /** The characters of Mac Roman from 0x80 to 0xFF. */
    private const string MAC_HIGH = 'ÄÅÇÉÑÖÜáàâäãåçéèêëíìîïñóòôöõúùûü†°¢£§•¶ß®©™´¨≠ÆØ∞±≤≥¥µ∂∑∏π∫ªºΩæø¿¡¬√ƒ≈∆«»…'
        ."\u{A0}".'ÀÃÕŒœ–—“”‘’÷◊ÿŸ⁄€‹›ﬁﬂ‡·‚„‰ÂÊÁËÈÍÎÏÌÓÔ'."\u{F8FF}".'ÒÚÛÙıˆ˜¯˘˙˚¸˝˛ˇ';

    /** @var array<int, string> the name `mbstring` gives each Windows or DOS code page it reads */
    private const array KNOWN = [
        1251 => 'Windows-1251',
        1252 => 'Windows-1252',
        1254 => 'Windows-1254',
        850 => 'CP850',
        866 => 'CP866',
        932 => 'SJIS-win',
        936 => 'CP936',
        949 => 'UHC',
        950 => 'CP950',
    ];

    /**
     * @param string $codepage a Windows code page number, such as `1252`, or `mac`
     */
    public static function decode(string $bytes, string $codepage = '1252'): string
    {
        if (self::MAC_ROMAN === $codepage) {
            return self::macRoman($bytes);
        }

        return mb_convert_encoding($bytes, 'UTF-8', self::KNOWN[(int) $codepage] ?? 'Windows-1252');
    }

    /**
     * The character of a code point; U+FFFD for a surrogate or a code beyond Unicode.
     */
    public static function character(int $code): string
    {
        if ($code < 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF)) {
            return "\u{FFFD}";
        }

        return mb_chr($code, 'UTF-8');
    }

    private static function macRoman(string $bytes): string
    {
        $high = mb_str_split(self::MAC_HIGH);
        $text = '';

        foreach (str_split($bytes) as $byte) {
            $code = \ord($byte[0]);
            $text .= $code < 0x80 ? $byte : $high[$code - 0x80];
        }

        return $text;
    }
}
