<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Xlsx;

use DateTimeImmutable;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Internal\Values;

/**
 * The serial numbers an XLSX workbook writes dates with: days since 1899-12-30, the time of day as the fraction. The
 * 1900 system counts a 29 February 1900 that never was, so its serial numbers before 1 March 1900 are one day off; the
 * 1904 system starts on 1904-01-01.
 *
 * @internal
 */
final class ExcelDate
{
    /** 1899-12-30T00:00:00Z, the day before the first serial number with the 1900 system's error. */
    private const int EPOCH = -2209161600;

    /** 1900-03-01T00:00:00Z, the first day the 1900 system counts right. */
    private const int FIRST_EXACT_DAY = -2203891200;

    /** The serial number of 9999-12-31, the last day a spreadsheet writes. */
    public const int LAST_SERIAL = 2958465;

    /** The days between the 1900 system and the 1904 system. */
    private const int DAYS_1904 = 1462;

    /**
     * The serial number of a date's wall-clock time.
     *
     * @throws WriteFailed when the date comes before 1 March 1900 or after 9999
     */
    public static function serial(DateTimeImmutable $date): int|float
    {
        $wall = Values::wallClock($date);
        $seconds = $wall->getTimestamp();

        if ($seconds < self::FIRST_EXACT_DAY || (int) $wall->format('Y') > 9999) {
            throw WriteFailed::because(\sprintf('An XLSX workbook writes the dates from 1900-03-01 to 9999-12-31, not %s.', $wall->format('Y-m-d')));
        }

        $seconds -= self::EPOCH;
        $micro = (int) $wall->format('u');

        if (0 === $seconds % 86400 && 0 === $micro) {
            return intdiv($seconds, 86400);
        }

        return ($seconds + $micro / 1_000_000) / 86400;
    }

    /**
     * The wall-clock time of a serial number, in UTC, to the millisecond; null outside the days a spreadsheet writes.
     */
    public static function date(float $serial, bool $date1904 = false): ?DateTimeImmutable
    {
        if ($date1904) {
            $serial += self::DAYS_1904;
        } elseif ($serial >= 1 && $serial < 61) {
            ++$serial;
        }

        if ($serial < 0 || $serial >= self::LAST_SERIAL + 1) {
            return null;
        }

        $days = (int) floor($serial);
        $milliseconds = (int) round(($serial - $days) * 86_400_000);
        $seconds = self::EPOCH + $days * 86400 + intdiv($milliseconds, 1000);

        return DateTimeImmutable::createFromTimestamp($seconds)->setMicrosecond($milliseconds % 1000 * 1000);
    }
}
