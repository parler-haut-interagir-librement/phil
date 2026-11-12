<?php

declare(strict_types=1);

namespace Phil\ApiContract\Http;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The HTTP-date of RFC 9110 §5.6.7, as the `Sunset` header carries it (RFC 8594).
 *
 * A sender writes only the preferred IMF-fixdate. A recipient must also accept the two obsolete forms, RFC 850
 * and asctime, and this reader does.
 */
final class HttpDate
{
    private const string IMF_FIXDATE = 'D, d M Y H:i:s \G\M\T';

    /**
     * RFC 850, with a two-digit year that RFC 9110 reads as within fifty years of the present; PHP's `y` reads
     * it within the 1970–2069 window, which is the same answer for any date a deprecation can carry.
     */
    private const string RFC_850 = 'l, d-M-y H:i:s \G\M\T';

    private const string ASCTIME = 'D M j H:i:s Y';

    private function __construct()
    {
    }

    public static function format(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format(self::IMF_FIXDATE);
    }

    /**
     * Returns `null` for a value that is not an HTTP-date, rather than throwing: a malformed header is a
     * header the recipient ignores.
     */
    public static function parse(string $value): ?DateTimeImmutable
    {
        // asctime pads a single-digit day with a space ("Nov  6"); collapse runs of spaces before matching.
        $value = preg_replace('/ {2,}/', ' ', trim($value)) ?? '';
        $utc = new DateTimeZone('UTC');

        foreach ([self::IMF_FIXDATE, self::RFC_850, self::ASCTIME] as $format) {
            // Every format names every field down to the second, so nothing is taken from the current time.
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $utc);

            // A date that PHP silently normalised (31 Feb, or a weekday that does not match) is not the date
            // written: formatting it back is the check, and it also refuses what PHP parsed with a warning.
            if (false !== $parsed && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        return null;
    }
}
