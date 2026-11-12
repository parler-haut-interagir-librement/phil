<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Internal;

use DateTimeImmutable;
use Phil\IpAnonymizer\Exception\InvalidOption;

/**
 * The period of the clock an instant falls in: the number of whole periods since the Unix epoch, counted in UTC
 * whatever the time zone of the clock, and how long the period still lasts.
 */
final readonly class SaltPeriod
{
    /**
     * @param int          $index       the whole periods since the Unix epoch
     * @param positive-int $secondsLeft
     */
    private function __construct(
        public string $saltId,
        public int $index,
        public int $secondsLeft,
    ) {
    }

    /**
     * @return positive-int the length, once checked
     *
     * @throws InvalidOption when the period is shorter than a second
     */
    public static function length(int $seconds): int
    {
        if ($seconds < 1) {
            throw InvalidOption::because('A salt period is at least one second.');
        }

        return $seconds;
    }

    /**
     * @param positive-int $seconds
     *
     * @throws InvalidOption when the instant is before the Unix epoch
     */
    public static function at(DateTimeImmutable $now, int $seconds): self
    {
        $timestamp = $now->getTimestamp();

        if ($timestamp < 0) {
            throw InvalidOption::because('Salt periods count from the Unix epoch; the clock reads an earlier instant.');
        }

        $index = intdiv($timestamp, $seconds);
        $secondsLeft = $seconds - $timestamp % $seconds;
        \assert($secondsLeft > 0);

        return new self($seconds.'-'.$index, $index, $secondsLeft);
    }
}
