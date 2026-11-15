<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidCue;

/**
 * Instants of timed text in integer milliseconds from the start of the media, and their written forms. Every
 * computation of the library is on integers: no float rounding builds up over a long track.
 */
final class Timecode
{
    /**
     * The last instant the library carries: 9999 hours, 59 minutes, 59.999 seconds.
     */
    public const int MAX = 35_999_999_999;

    private const int HOUR = 3_600_000;

    private const int MINUTE = 60_000;

    private function __construct()
    {
    }

    /**
     * Reads a WebVTT timestamp, `hh:mm:ss.ttt` or `mm:ss.ttt`; null when the text is not one.
     */
    public static function parseWebVtt(string $text): ?int
    {
        if (1 !== preg_match('/^(?:(\d{2,4}):)?([0-5]\d):([0-5]\d)\.(\d{3})$/D', $text, $match)) {
            return null;
        }

        return (int) $match[1] * self::HOUR + (int) $match[2] * self::MINUTE + (int) $match[3] * 1000 + (int) $match[4];
    }

    /**
     * Reads an SRT timestamp, `hh:mm:ss,mmm`, also with a full stop or fewer digits as files written by hand have;
     * null when the text is not one.
     */
    public static function parseSrt(string $text): ?int
    {
        if (1 !== preg_match('/^(\d{1,4}):([0-5]?\d):([0-5]?\d)[,.](\d{1,3})$/D', $text, $match)) {
            return null;
        }

        return (int) $match[1] * self::HOUR + (int) $match[2] * self::MINUTE + (int) $match[3] * 1000
            + (int) str_pad($match[4], 3, '0');
    }

    /**
     * Reads a clock as people write it beside a paragraph, `h:mm:ss` or `m:ss`, with milliseconds or not; null
     * otherwise.
     */
    public static function parseClock(string $text): ?int
    {
        if (1 !== preg_match('/^(?:(\d{1,4}):)?([0-5]?\d):([0-5]\d)(?:[.,](\d{1,3}))?$/D', trim($text), $match)) {
            return null;
        }

        return (int) $match[1] * self::HOUR + (int) $match[2] * self::MINUTE + (int) $match[3] * 1000
            + (int) str_pad($match[4] ?? '', 3, '0');
    }

    /**
     * Reads seconds as JSON gives them, an integer or a decimal, rounded to the millisecond; null when they are
     * negative, past `MAX` or not finite.
     */
    public static function fromSeconds(int|float $seconds): ?int
    {
        if (!is_finite((float) $seconds) || $seconds < 0 || $seconds * 1000 > self::MAX) {
            return null;
        }

        return (int) round($seconds * 1000);
    }

    /**
     * Seconds as JSON writes them: an integer when the instant falls on a second.
     *
     * @throws InvalidCue when the instant is negative or past `MAX`
     */
    public static function toSeconds(int $milliseconds): int|float
    {
        self::check($milliseconds);

        return 0 === $milliseconds % 1000 ? intdiv($milliseconds, 1000) : $milliseconds / 1000;
    }

    /**
     * `hh:mm:ss.ttt`, the hours always written.
     *
     * @throws InvalidCue when the instant is negative or past `MAX`
     */
    public static function formatWebVtt(int $milliseconds): string
    {
        return self::format($milliseconds, '.');
    }

    /**
     * `hh:mm:ss,mmm`.
     *
     * @throws InvalidCue when the instant is negative or past `MAX`
     */
    public static function formatSrt(int $milliseconds): string
    {
        return self::format($milliseconds, ',');
    }

    /**
     * The clock a reader sees beside a paragraph: `m:ss` under an hour, `h:mm:ss` from the first hour on.
     *
     * @throws InvalidCue when the instant is negative or past `MAX`
     */
    public static function formatClock(int $milliseconds): string
    {
        self::check($milliseconds);
        $seconds = intdiv($milliseconds, 1000);
        $hours = intdiv($seconds, 3600);

        return $hours > 0
            ? \sprintf('%d:%02d:%02d', $hours, intdiv($seconds % 3600, 60), $seconds % 60)
            : \sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * The ISO 8601 duration of an HTML `datetime` attribute, `PT75.5S`.
     *
     * @throws InvalidCue when the instant is negative or past `MAX`
     */
    public static function formatDuration(int $milliseconds): string
    {
        return 'PT'.self::toSeconds($milliseconds).'S';
    }

    /**
     * Reads the duration `formatDuration()` writes, with hours and minutes too; null otherwise.
     */
    public static function parseDuration(string $text): ?int
    {
        if ('PT' === $text || 1 !== preg_match('/^PT(?:(\d{1,4})H)?(?:(\d{1,6})M)?(?:(\d{1,8})(?:\.(\d{1,3}))?S)?$/D', $text, $match)) {
            return null;
        }

        $value = (int) ($match[1] ?? 0) * self::HOUR + (int) ($match[2] ?? 0) * self::MINUTE + (int) ($match[3] ?? 0) * 1000
            + (int) str_pad($match[4] ?? '', 3, '0');

        return $value <= self::MAX ? $value : null;
    }

    /**
     * @throws InvalidCue when the instant is negative or past `MAX`
     */
    public static function check(int $milliseconds): void
    {
        if ($milliseconds < 0 || $milliseconds > self::MAX) {
            throw new InvalidCue(\sprintf('%d ms is not an instant between 0 and %d ms.', $milliseconds, self::MAX));
        }
    }

    private static function format(int $milliseconds, string $separator): string
    {
        self::check($milliseconds);

        return \sprintf(
            '%02d:%02d:%02d%s%03d',
            intdiv($milliseconds, self::HOUR),
            intdiv($milliseconds % self::HOUR, self::MINUTE),
            intdiv($milliseconds % self::MINUTE, 1000),
            $separator,
            $milliseconds % 1000,
        );
    }
}
