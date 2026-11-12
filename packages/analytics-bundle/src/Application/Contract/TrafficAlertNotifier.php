<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\Alert\TrafficAlert;

interface TrafficAlertNotifier
{
    public function notify(TrafficAlert $alert): void;
}
