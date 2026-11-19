<?php

declare(strict_types=1);

namespace Phil\MimeReader\Internal;

use ValueError;

/**
 * Text of a declared character set turned into UTF-8, by mbstring, or by iconv for a set mbstring does not know, such
 * as TIS-620 or Macintosh. A missing, unknown or refused set reads the text as UTF-8 with its invalid sequences
 * replaced: the result is always valid UTF-8.
 *
 * @internal
 */
final class Charset
{
    /**
     * The labels mail writes for a set mbstring knows by another name. ISO-8859-1 is read as Windows-1252, its superset,
     * as browsers do: mail labelled Latin-1 often holds Windows quotes and dashes.
     */
    private const array ALIASES = [
        'latin1' => 'Windows-1252',
        'l1' => 'Windows-1252',
        'iso-8859-1' => 'Windows-1252',
        'iso8859-1' => 'Windows-1252',
        'iso_8859-1' => 'Windows-1252',
        'cp1252' => 'Windows-1252',
        'cp1251' => 'Windows-1251',
        'cp1254' => 'Windows-1254',
        'gb2312' => 'GB18030',
        'gbk' => 'GB18030',
        'ks_c_5601-1987' => 'UHC',
        'big5' => 'BIG-5',
        'shift_jis' => 'SJIS',
        'x-sjis' => 'SJIS',
        'unicode-1-1-utf-7' => 'UTF-7',
    ];

    /**
     * What mbstring converts but is no character set: a transfer encoding, entities, or its own internal names.
     */
    private const array REFUSED = [
        'base64', 'uuencode', 'html-entities', 'html', 'quoted-printable', 'qprint', '7bit', '8bit', 'pass', 'auto',
        'wchar',
    ];

    private const array UTF8 = ['', 'utf-8', 'utf8', 'us-ascii', 'ascii'];

    public static function toUtf8(string $bytes, ?string $charset): string
    {
        $name = strtolower(trim($charset ?? ''));

        if (\in_array($name, self::UTF8, true) || \in_array($name, self::REFUSED, true)) {
            return mb_scrub($bytes, 'UTF-8');
        }

        try {
            return (string) mb_convert_encoding($bytes, 'UTF-8', self::ALIASES[$name] ?? $name);
        } catch (ValueError) {
            return mb_scrub(self::iconv($bytes, $name) ?? $bytes, 'UTF-8');
        }
    }

    /**
     * The text converted by iconv, or `null` when iconv does not know the set either. A label carrying a `/` is
     * refused: iconv would read what follows `//` as its own options.
     */
    private static function iconv(string $bytes, string $name): ?string
    {
        if (str_contains($name, '/')) {
            return null;
        }

        $text = @iconv($name, 'UTF-8//IGNORE', $bytes);

        return false === $text ? null : $text;
    }
}
