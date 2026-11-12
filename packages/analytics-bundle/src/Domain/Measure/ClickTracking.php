<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

/**
 * How far clicks are measured on a site. The default is {@see self::Aggregate}: a counter per declared name, tied to
 * nobody. This bundle has no level per person: it keeps no identifier a click could be tied to.
 */
enum ClickTracking: string
{
    case Off = 'off';
    case Aggregate = 'aggregate';

    public function counts(): bool
    {
        return self::Aggregate === $this;
    }
}
