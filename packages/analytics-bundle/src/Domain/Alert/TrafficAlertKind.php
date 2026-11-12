<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Alert;

enum TrafficAlertKind: string
{
    case Spike = 'spike';
    case Dip = 'dip';
}
