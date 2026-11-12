<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

/**
 * How long a page was in front of its reader, in closed bands: the only form of a duration a counter holds.
 */
enum ActiveTime: string
{
    case UnderTenSeconds = 'under_10s';
    case UnderThirtySeconds = '10s_30s';
    case UnderAMinute = '30s_1m';
    case UnderThreeMinutes = '1m_3m';
    case UnderTenMinutes = '3m_10m';
    case Longer = 'over_10m';

    public static function ofSeconds(int $seconds): self
    {
        return match (true) {
            $seconds < 10 => self::UnderTenSeconds,
            $seconds < 30 => self::UnderThirtySeconds,
            $seconds < 60 => self::UnderAMinute,
            $seconds < 180 => self::UnderThreeMinutes,
            $seconds < 600 => self::UnderTenMinutes,
            default => self::Longer,
        };
    }
}
