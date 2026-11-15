<?php

declare(strict_types=1);

namespace Phil\TimedText\Internal;

use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Limits;

/**
 * A document checked against the limits before it is read: its size, its byte order mark removed, its invalid UTF-8
 * replaced. Not part of the public API.
 *
 * @internal
 */
final class Input
{
    private function __construct()
    {
    }

    /**
     * @throws LimitExceeded when the document is larger than the limits allow
     */
    public static function text(string $input, Limits $limits): string
    {
        if (\strlen($input) > $limits->maxBytes) {
            throw LimitExceeded::bytes(\strlen($input), $limits->maxBytes);
        }

        if (str_starts_with($input, "\u{FEFF}")) {
            $input = substr($input, 3);
        }

        return mb_check_encoding($input, 'UTF-8') ? $input : mb_scrub($input, 'UTF-8');
    }

    /**
     * The lines of the document, split at CRLF, CR or LF.
     *
     * @return non-empty-list<string>
     *
     * @throws LimitExceeded when the document is larger than the limits allow
     */
    public static function lines(string $input, Limits $limits): array
    {
        $lines = preg_split('/\r\n|\r|\n/', self::text($input, $limits));

        return false === $lines || [] === $lines ? [''] : $lines;
    }

    /**
     * @throws LimitExceeded when the text is longer than the limits allow
     */
    public static function checkText(string $text, ?int $line, Limits $limits): string
    {
        if (\strlen($text) > $limits->maxTextBytes) {
            throw LimitExceeded::text($line, $limits->maxTextBytes);
        }

        return $text;
    }
}
