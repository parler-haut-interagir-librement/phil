<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

/**
 * What enforces a retention period.
 *
 * **A schedule nothing executes is a document rather than a control**, so every rule names its mechanism, and
 * the one that has none says so: `Manual`.
 */
enum Mechanism: string
{
    /** A scheduled purge that this bundle drives, through a `RetentionPurger`. */
    case Job = 'job';

    /** The store expires its own entries: a cache TTL, a token expiry. */
    case Ttl = 'ttl';

    /** Log or file rotation, configured where the files are written. */
    case Rotation = 'rotation';

    /** Removed by an action or a domain event: a user removing an address, a lifecycle transition. */
    case Event = 'event';

    /** Nothing automatic: a person purges, at a cadence the application records. */
    case Manual = 'manual';
}
