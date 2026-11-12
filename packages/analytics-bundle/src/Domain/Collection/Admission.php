<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Collection;

/**
 * What a page view does to the counters, as {@see AdmissionRule} decides it.
 */
enum Admission: string
{
    /** The site is suspended or retired: the request is refused without a write. */
    case SiteNotCollecting = 'site_not_collecting';

    /** The browser sent Do Not Track or Global Privacy Control: nothing is counted. */
    case OptedOut = 'opted_out';

    /** The user agent declares itself a crawler: nothing is counted. */
    case DeclaredCrawler = 'declared_crawler';

    /** The referrer is on the spam list: nothing is counted. */
    case ReferrerSpam = 'referrer_spam';

    /**
     * The browser's verdict is robot: only the aggregate robot-verdict counter advances
     * (`Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter`), never the audience.
     */
    case RobotVerdictOnly = 'robot_verdict_only';

    /**
     * No usable verdict came with the page view: nothing is counted, neither the audience nor the robot-verdict
     * counter. A missing verdict is not a robot verdict.
     */
    case NoVerdict = 'no_verdict';

    /** The page view advances the audience counters. */
    case CountAudience = 'count_audience';

    public function advancesAudience(): bool
    {
        return self::CountAudience === $this;
    }

    public function advancesRobotVerdictCounter(): bool
    {
        return self::RobotVerdictOnly === $this;
    }

    /**
     * Whether the request is refused outright, rather than accepted and not counted.
     */
    public function isRefusal(): bool
    {
        return self::SiteNotCollecting === $this;
    }
}
