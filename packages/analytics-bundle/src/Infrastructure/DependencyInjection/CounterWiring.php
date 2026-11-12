<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\DependencyInjection;

/**
 * What the configuration wires of the counters.
 */
enum CounterWiring
{
    /** No store: the "dbal" store without DoctrineBundle. */
    case None;

    /** A store of the application, without a rollup: no retention. */
    case StoreOnly;

    /** A store and its rollup. */
    case WithRollup;
}
