<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Ods;

use Phil\Spreadsheet\Exception\WriteFailed;

/**
 * The texts of an OpenDocument cell: one `<text:p>` per line, a tab as `<text:tab/>`, and the spaces an XML reader
 * would collapse — at either end, or more than one in a row — as `<text:s/>`. The control characters XML cannot hold
 * become U+FFFD.
 *
 * @internal
 */
final class OdsText
{
    /**
     * @throws WriteFailed when the text is not UTF-8
     */
    public static function paragraphs(string $text): string
    {
        $escaped = htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');

        if ('' === $escaped && '' !== $text) {
            throw WriteFailed::because('A text to write is not UTF-8.');
        }

        if (false !== strpbrk($escaped, "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F") || str_contains($escaped, "\u{FFFE}") || str_contains($escaped, "\u{FFFF}")) {
            $escaped = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]|\x{FFFE}|\x{FFFF}/u', "\u{FFFD}", $escaped);
        }

        if (false === strpbrk($escaped, "\t\r\n") && !str_contains($escaped, '  ') && trim($escaped, ' ') === $escaped) {
            return '<text:p>'.$escaped.'</text:p>';
        }

        $xml = '';

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $escaped)) as $line) {
            $line = str_replace("\t", '<text:tab/>', $line);
            $line = (string) preg_replace_callback(
                '/(?<=^|>) +| +(?=$|<)|(?<= ) +/',
                static fn (array $match): string => 1 === \strlen($match[0]) ? '<text:s/>' : '<text:s text:c="'.\strlen($match[0]).'"/>',
                $line,
            );
            $xml .= '<text:p>'.$line.'</text:p>';
        }

        return $xml;
    }
}
