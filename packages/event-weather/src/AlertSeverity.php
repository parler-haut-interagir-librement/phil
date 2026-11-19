<?php

declare(strict_types=1);

namespace Phil\EventWeather;

/**
 * The severity of an alert, the scale of the Common Alerting Protocol, from the most severe.
 */
enum AlertSeverity: string
{
    case Extreme = 'Extreme';
    case Severe = 'Severe';
    case Moderate = 'Moderate';
    case Minor = 'Minor';
    case Unknown = 'Unknown';

    /**
     * Whether this severity is at least as high as another.
     */
    public function isAtLeast(self $other): bool
    {
        $order = [self::Extreme, self::Severe, self::Moderate, self::Minor, self::Unknown];

        return array_search($this, $order, true) <= array_search($other, $order, true);
    }
}
