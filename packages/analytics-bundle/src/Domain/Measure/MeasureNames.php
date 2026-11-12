<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

/**
 * The metrics the bundle writes, and what is said of them everywhere they are read.
 */
final readonly class MeasureNames
{
    public const string PAGEVIEWS = 'pageviews';

    public const string VISITS = 'visits';

    public const string TRANSITIONS = 'transitions';

    public const string GOALS = 'goals';

    public const string CLICKS = 'clicks';

    public const string EVENTS = 'events';

    public const string OUTBOUND = 'outbound';

    public const string OUTBOUND_LINK = 'outbound_link';

    public const string SEARCH = 'search';

    public const string ENGAGEMENT = 'engagement';

    /**
     * The events the application's server counts for a site, under the dimension `event`; and, under the metric
     * `server_events.<event>`, the values of the site's declared dimensions.
     */
    public const string SERVER_EVENTS = 'server_events';

    /**
     * The page views whose verdict is a robot, under the dimension `verdict`: never part of the audience.
     */
    public const string ROBOT_VERDICTS = 'robot_verdicts';

    /**
     * The dimensions whose values are not a closed list, each behind the small-number rule.
     */
    public const array OPEN_DIMENSIONS = ['host', 'term'];

    /**
     * The exact count of each author's links and what people search for stay with those who run the site.
     */
    public const array PRIVATE_METRICS = [self::OUTBOUND_LINK, self::SEARCH];
}
