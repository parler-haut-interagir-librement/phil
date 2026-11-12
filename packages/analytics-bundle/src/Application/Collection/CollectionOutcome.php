<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Collection;

/**
 * What became of one collection request.
 */
enum CollectionOutcome: string
{
    /** Admitted: the audience counters, or the robot-verdict counter, advanced. */
    case Counted = 'counted';

    /** Accepted and counted nowhere: a privacy signal, a declared crawler, referrer spam or no verdict. */
    case NotCounted = 'not_counted';

    /** No site declares the origin, the site does not collect, or the client address is not an address. */
    case Refused = 'refused';

    /** Over the site's budget for this client, or the limiter could not answer. */
    case OverBudget = 'over_budget';

    /** A body longer than collection reads. */
    case TooLarge = 'too_large';

    /**
     * The HTTP status a collection endpoint answers with: no body, whatever the outcome.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::Counted, self::NotCounted => 204,
            self::Refused => 403,
            self::OverBudget => 429,
            self::TooLarge => 413,
        };
    }
}
