<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Messenger;

use Phil\AnalyticsBundle\Infrastructure\BrokenFlow\GoalCompletionHistory;
use Phil\BrokenFlowAlerts\BrokenFlowWatch;

final readonly class CheckBrokenFlowsHandler
{
    public function __construct(
        private BrokenFlowWatch $watch,
        private GoalCompletionHistory $goals,
    ) {
    }

    public function __invoke(): void
    {
        $this->watch->check($this->goals->flows());
    }
}
