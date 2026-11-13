<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use DateTimeImmutable;
use Phil\IncidentMode\Exception\InvalidIncident;

/**
 * How long an incident lasts, in whole seconds: at least one minute, at most ninety days. An incident never lasts
 * without an end.
 */
final readonly class Duration
{
    public const int MINIMUM = 60;

    public const int MAXIMUM = 90 * 86_400;

    private function __construct(
        public int $seconds,
    ) {
    }

    public static function ofSeconds(int $seconds): self
    {
        if ($seconds < self::MINIMUM || $seconds > self::MAXIMUM) {
            throw InvalidIncident::because(\sprintf('An incident lasts from %d seconds to %d seconds; %d was given.', self::MINIMUM, self::MAXIMUM, $seconds));
        }

        return new self($seconds);
    }

    public static function ofMinutes(int $minutes): self
    {
        return self::ofSeconds(self::times($minutes, 60));
    }

    public static function ofHours(int $hours): self
    {
        return self::ofSeconds(self::times($hours, 3_600));
    }

    public static function ofDays(int $days): self
    {
        return self::ofSeconds(self::times($days, 86_400));
    }

    public function after(DateTimeImmutable $start): DateTimeImmutable
    {
        return $start->modify(\sprintf('+%d seconds', $this->seconds));
    }

    public function isLongerThan(self $other): bool
    {
        return $this->seconds > $other->seconds;
    }

    /**
     * The product, or a number outside the bounds when it would overflow, so that the bounds refuse it.
     */
    private static function times(int $count, int $unit): int
    {
        return $count > intdiv(\PHP_INT_MAX, $unit) || $count < intdiv(\PHP_INT_MIN, $unit) ? -1 : $count * $unit;
    }
}
