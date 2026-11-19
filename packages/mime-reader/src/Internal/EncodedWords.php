<?php

declare(strict_types=1);

namespace Phil\MimeReader\Internal;

/**
 * The encoded words of a header value (RFC 2047) decoded to UTF-8. The space between two encoded words is dropped, the
 * bytes of adjacent words in the same set are joined before conversion so that a character split between them is read
 * whole, and the text around them is kept, its invalid UTF-8 replaced.
 *
 * @internal
 */
final class EncodedWords
{
    private const string WORD = '=\?([^?\s*]{1,64})(?:\*[^?\s]{0,32})?\?([BbQq])\?([^?\s]*)\?=';

    public static function decode(string $value): string
    {
        if (!str_contains($value, '=?')) {
            return mb_scrub($value, 'UTF-8');
        }

        $tokens = preg_split('/('.self::WORD.')/', $value, -1, \PREG_SPLIT_DELIM_CAPTURE);

        if (false === $tokens) {
            return mb_scrub($value, 'UTF-8');
        }

        $decoded = '';
        $pending = '';
        $pendingCharset = null;
        $count = \count($tokens);

        // preg_split gives the text, then for each word the whole word and its three groups, then the text after it.
        for ($i = 0; $i < $count; $i += 5) {
            $text = $tokens[$i];

            if ($i + 4 < $count) {
                $charset = strtolower($tokens[$i + 2]);
                $bytes = self::bytes(strtoupper($tokens[$i + 3]), $tokens[$i + 4]);
                $betweenWords = $i > 0 && '' === trim($text, " \t\r\n");

                if (!$betweenWords || $charset !== $pendingCharset) {
                    $decoded .= self::flush($pending, $pendingCharset).($betweenWords ? '' : mb_scrub($text, 'UTF-8'));
                    $pending = '';
                }

                $pending .= $bytes;
                $pendingCharset = $charset;

                continue;
            }

            $decoded .= self::flush($pending, $pendingCharset).mb_scrub($text, 'UTF-8');
        }

        return $decoded;
    }

    private static function bytes(string $encoding, string $text): string
    {
        if ('B' === $encoding) {
            return Base64::decode($text);
        }

        return quoted_printable_decode(str_replace('_', ' ', $text));
    }

    private static function flush(string $bytes, ?string $charset): string
    {
        return '' === $bytes ? '' : Charset::toUtf8($bytes, $charset);
    }
}
