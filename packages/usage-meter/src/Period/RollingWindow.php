<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Period;

use DateTimeImmutable;
use Override;
use Phil\UsageMeter\Exception\InvalidUsage;

/**
 * The last so many seconds, counted in buckets: a window of 24 hours in 24 buckets counts what was used in the
 * current hour and the 23 before, and frees an hour's use as that hour leaves the window. Buckets are aligned on the
 * Unix epoch, so every subject shares them; one bucket makes fixed blocks of the window's length.
 */
final readonly class RollingWindow implements PeriodPolicy
{
    /** The length of a bucket in seconds. */
    public int $bucketSeconds;

    public function __construct(
        /** The length of the window in seconds. */
        public int $seconds,
        /** How many buckets the window is cut into, 1 to 1440; they divide it exactly. */
        public int $buckets = 1,
    ) {
        if ($seconds < 1 || $buckets < 1 || $buckets > 1440 || 0 !== $seconds % $buckets) {
            throw InvalidUsage::because(\sprintf('A rolling window lasts one second or more and is cut into 1 to 1440 buckets that divide it exactly; got %d seconds in %d buckets.', $seconds, $buckets));
        }

        $this->bucketSeconds = intdiv($seconds, $buckets);
    }

    /**
     * The last `$minutes` minutes, one bucket per minute.
     */
    public static function minutes(int $minutes): self
    {
        return new self($minutes * 60, $minutes);
    }

    /**
     * The last `$hours` hours, one bucket per hour.
     */
    public static function hours(int $hours): self
    {
        return new self($hours * 3600, $hours);
    }

    /**
     * The last `$days` days, one bucket per day.
     */
    public static function days(int $days): self
    {
        return new self($days * 86400, $days);
    }

    #[Override]
    public function windowAt(DateTimeImmutable $at, ?DateTimeImmutable $anchor = null): Window
    {
        $now = $at->getTimestamp();
        $bucket = $now - ($now % $this->bucketSeconds + $this->bucketSeconds) % $this->bucketSeconds;
        $end = $bucket + $this->bucketSeconds;

        return new Window($this->instant($end - $this->seconds), $this->instant($end), $this->instant($bucket), $this->instant($bucket + $this->seconds));
    }

    private function instant(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$timestamp);
    }
}
