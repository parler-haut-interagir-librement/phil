<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\BrokenFlowAlerts\Contract\CompletionHistory;
use Phil\BrokenFlowAlerts\Flow;

/**
 * A history given per flow, newest day last; the days asked for are recorded.
 */
final class ScriptedHistory implements CompletionHistory
{
    /**
     * @var list<array{string, string, int}>
     */
    public array $asked = [];

    /**
     * @param array<string, list<int>> $histories
     */
    public function __construct(
        private readonly array $histories,
    ) {
    }

    #[Override]
    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array
    {
        $this->asked[] = [$flow->id, $firstDay->format(\DATE_ATOM), $days];

        return $this->histories[$flow->id] ?? [];
    }
}
