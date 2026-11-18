<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;

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

    /**
     * Every metric the bundle writes under its own name.
     */
    public const array METRICS = [
        self::PAGEVIEWS,
        self::VISITS,
        self::TRANSITIONS,
        self::GOALS,
        self::CLICKS,
        self::EVENTS,
        self::OUTBOUND,
        self::OUTBOUND_LINK,
        self::SEARCH,
        self::ENGAGEMENT,
        self::SERVER_EVENTS,
        self::ROBOT_VERDICTS,
    ];

    /**
     * The metrics also written as `<metric>.<name>`: a goal's declared dimensions under `goals.<goal>`, a server
     * event's under `server_events.<event>`.
     */
    public const array NAMED_METRICS = [self::GOALS, self::SERVER_EVENTS];

    /**
     * Whether a metric is one the bundle writes, in its canonical form: one of {@see METRICS}, or one of
     * {@see NAMED_METRICS} followed by a dot and a name of {@see CounterKey::NAME_PATTERN}. The comparison is exact:
     * `OUTBOUND_LINK` or `Pageviews` is no metric, whatever the collation of the table would make of it.
     */
    public static function isMetric(string $metric): bool
    {
        if (\in_array($metric, self::METRICS, true)) {
            return true;
        }

        $parts = explode('.', $metric, 2);

        return 2 === \count($parts)
            && \in_array($parts[0], self::NAMED_METRICS, true)
            && 1 === preg_match(CounterKey::NAME_PATTERN, $metric)
            && 1 === preg_match(CounterKey::NAME_PATTERN, $parts[1]);
    }
}
