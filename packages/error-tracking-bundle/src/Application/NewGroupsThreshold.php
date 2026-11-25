<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

/**
 * The bounds of the threshold of new groups the health check reads, kept out of the check so that the configuration
 * reads them without the package of the check installed.
 */
final class NewGroupsThreshold
{
    /** The groups the check reads at most, which is also the highest threshold. */
    public const int MAX = 200;

    private function __construct()
    {
    }
}
