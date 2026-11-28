<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

/**
 * A PDF with its compressed streams inflated, so that a test reads the text it draws.
 */
final class PdfText
{
    public static function readable(string $pdf): string
    {
        $readable = '';
        $at = 0;

        while (1 === preg_match('/\/Filter \/FlateDecode \/Length (\d+) >>\nstream\n/', $pdf, $match, \PREG_OFFSET_CAPTURE, $at)) {
            $start = $match[0][1] + \strlen($match[0][0]);
            $data = substr($pdf, $start, (int) $match[1][0]);
            $readable .= substr($pdf, $at, $start - $at).gzuncompress($data);
            $at = $start + \strlen($data);
        }

        return $readable.substr($pdf, $at);
    }

    /**
     * @return list<string> each text drawn, in order
     */
    public static function texts(string $pdf): array
    {
        preg_match_all('/\((.*?)(?<!\\\\)\) Tj/s', self::readable($pdf), $texts);

        return array_map(static fn (string $text): string => mb_convert_encoding(stripcslashes($text), 'UTF-8', 'Windows-1252'), $texts[1]);
    }
}
