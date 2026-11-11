<?php

declare(strict_types=1);

namespace Phil\UploadScan\Clamd;

use Phil\UploadScan\ScanResult;

/**
 * The answers of clamd to the `z` commands, each ended by a null byte: `stream: OK`, `stream: <signature> FOUND` and
 * `<message> ERROR` to `INSTREAM`; `PONG` to `PING`; `ClamAV <version>/<signatures>/<date>` to `VERSION`. Whatever
 * the bytes, the reader answers a result: a signature or a message is cut to its printable ASCII, and anything else is
 * an error result. An answer that says `FOUND` is an infection, even when its signature is unreadable.
 */
final class ClamdResponse
{
    /** The longest answer read from clamd, in bytes. */
    public const int MAX_BYTES = 4096;

    private const string STREAM = 'stream: ';

    private function __construct()
    {
    }

    public static function scan(string $answer): ScanResult
    {
        $text = self::text($answer);

        if ('' === $text) {
            return ScanResult::error('clamd closed the connection without an answer.');
        }

        if (str_starts_with($text, self::STREAM) && str_ends_with($text, ' FOUND')) {
            return ScanResult::infected(self::printable(substr($text, \strlen(self::STREAM), -\strlen(' FOUND')), 'Unnamed'));
        }

        if (self::STREAM.'OK' === $text) {
            return ScanResult::clean();
        }

        if (str_ends_with($text, 'ERROR')) {
            $message = substr($text, 0, -\strlen('ERROR'));

            return ScanResult::error(self::printable('' === $message ? 'clamd: error' : 'clamd: '.$message));
        }

        return ScanResult::error(self::printable('clamd answered: '.$text));
    }

    public static function isPong(string $answer): bool
    {
        return 'PONG' === self::text($answer);
    }

    /**
     * The version clamd states, or `null` for an answer that is not one.
     */
    public static function version(string $answer): ?string
    {
        $text = self::text($answer);

        return str_starts_with($text, 'ClamAV ') ? self::printable($text) : null;
    }

    /**
     * The answer up to its null byte, without the line ends and spaces around it.
     */
    private static function text(string $answer): string
    {
        $end = strpos($answer, "\0");

        return trim(false === $end ? $answer : substr($answer, 0, $end), " \t\r\n");
    }

    /**
     * The text with each byte outside printable ASCII replaced by `?`, cut to what a result keeps.
     */
    private static function printable(string $text, string $fallback = '?'): string
    {
        $text = trim(substr((string) preg_replace('/[^\x20-\x7E]/', '?', $text), 0, ScanResult::MAX_TEXT_BYTES));

        return '' === $text ? $fallback : $text;
    }
}
