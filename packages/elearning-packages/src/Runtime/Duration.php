<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * A span of time in hundredths of a second, as the session time and the total time of SCORM write it: `HHHH:MM:SS.SS`
 * in SCORM 1.2 — two to four digits of hours — and a duration of ISO 8601 in SCORM 2004, such as `PT1H30M5.25S`. A
 * year of ISO 8601 counts 365 days and a month 30, as no calendar anchors a session. Bounded at 9 999 hours, the most
 * SCORM 1.2 writes.
 */
final readonly class Duration
{
    public const int MAX_CENTISECONDS = 9_999 * 360_000 + 359_999;

    private function __construct(
        public int $centiseconds,
    ) {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofCentiseconds(int $centiseconds): self
    {
        return new self(max(0, min(self::MAX_CENTISECONDS, $centiseconds)));
    }

    /**
     * The span a value of the version writes; null when it is not one.
     */
    public static function parse(ScormVersion $version, string $value): ?self
    {
        return ScormVersion::Scorm12 === $version ? self::parseTimespan($value) : self::parseInterval($value);
    }

    /**
     * `HHHH:MM:SS.SS`, with two to four digits of hours, and one or two of hundredths.
     */
    public static function parseTimespan(string $value): ?self
    {
        if (1 !== preg_match('/^(\d{2,4}):([0-5]\d):([0-5]\d)(?:\.(\d{1,2}))?$/D', $value, $match)) {
            return null;
        }

        return self::ofCentiseconds((((int) $match[1] * 60 + (int) $match[2]) * 60 + (int) $match[3]) * 100 + (int) str_pad($match[4] ?? '0', 2, '0'));
    }

    /**
     * A duration of ISO 8601 with at least one component: years, months, days, hours, minutes and seconds with up to
     * two decimals.
     */
    public static function parseInterval(string $value): ?self
    {
        if (\strlen($value) > 64 || 1 !== preg_match('/^P(?=\d|T\d)(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?=\d)(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)(?:\.(\d{1,2}))?S)?)?$/D', $value, $match)) {
            return null;
        }

        $days = (int) ($match[1] ?? 0) * 365 + (int) ($match[2] ?? 0) * 30 + (int) ($match[3] ?? 0);
        $seconds = (($days * 24 + (int) ($match[4] ?? 0)) * 60 + (int) ($match[5] ?? 0)) * 60 + (int) ($match[6] ?? 0);

        if ($seconds > intdiv(self::MAX_CENTISECONDS, 100)) {
            return new self(self::MAX_CENTISECONDS);
        }

        return self::ofCentiseconds($seconds * 100 + (int) str_pad($match[7] ?? '0', 2, '0'));
    }

    public function plus(self $other): self
    {
        return self::ofCentiseconds($this->centiseconds + $other->centiseconds);
    }

    public function format(ScormVersion $version): string
    {
        return ScormVersion::Scorm12 === $version ? $this->timespan() : $this->interval();
    }

    /**
     * `HHHH:MM:SS.SS`.
     */
    public function timespan(): string
    {
        $hundredths = $this->centiseconds % 100;
        $seconds = intdiv($this->centiseconds, 100);

        return \sprintf('%04d:%02d:%02d.%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, $hundredths);
    }

    /**
     * `PT…H…M…S`, the seconds with their hundredths when there are some.
     */
    public function interval(): string
    {
        $hundredths = $this->centiseconds % 100;
        $seconds = intdiv($this->centiseconds, 100);

        return \sprintf('PT%dH%dM%d%sS', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, 0 === $hundredths ? '' : \sprintf('.%02d', $hundredths));
    }
}
