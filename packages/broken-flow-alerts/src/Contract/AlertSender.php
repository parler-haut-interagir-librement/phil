<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Contract;

use Phil\BrokenFlowAlerts\FlowAlert;

/**
 * Tells someone that a flow stopped completing, or completes again.
 */
interface AlertSender
{
    public function send(FlowAlert $alert): void;
}
