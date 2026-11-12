<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

/**
 * What the collection script may report besides a page view. **A closed list**: an event of another name counts
 * nothing.
 */
enum MeasureEvent: string
{
    case Click = 'click';
    case Download = 'download';
    case Engagement = 'engagement';
    case FormSubmit = 'form_submit';
    case NotFound = 'not_found';
    case Outbound = 'outbound';
    case Search = 'search';

    /**
     * Whether the event comes from a click, and so stops with {@see ClickTracking::Off}.
     */
    public function isClick(): bool
    {
        return match ($this) {
            self::Click, self::Download, self::Outbound => true,
            self::Engagement, self::FormSubmit, self::NotFound, self::Search => false,
        };
    }
}
