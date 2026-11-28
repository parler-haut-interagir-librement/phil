<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * Why a site needs attention.
 */
enum AlertKind: string
{
    case Unreachable = 'unreachable';
    case HealthDegraded = 'health_degraded';
    case HealthDown = 'health_down';
    case VersionBehind = 'version_behind';
}
