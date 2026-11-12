<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Tests\Unit\Fixture;

use Override;
use Phil\BrokenFlowAlerts\Contract\AlertSender;
use Phil\BrokenFlowAlerts\FlowAlert;

final class RecordingSender implements AlertSender
{
    /**
     * @var list<FlowAlert>
     */
    public array $sent = [];

    #[Override]
    public function send(FlowAlert $alert): void
    {
        $this->sent[] = $alert;
    }
}
