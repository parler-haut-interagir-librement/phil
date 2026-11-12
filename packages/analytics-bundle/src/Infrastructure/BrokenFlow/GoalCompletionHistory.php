<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\BrokenFlow;

use DateTimeImmutable;
use Override;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\BrokenFlowAlerts\Contract\CompletionHistory;
use Phil\BrokenFlowAlerts\Flow;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The goals of the sites as flows for `phil/broken-flow-alerts`: a flow's completions on a day are its goal's counter
 * on that day, read from the counter store and never written.
 *
 * @phpstan-type WatchedGoal array{site: string, goal: string, label: ?string}
 */
final readonly class GoalCompletionHistory implements CompletionHistory
{
    /**
     * @var array<string, array{SiteId, string}>
     */
    private array $goals;

    /**
     * @var list<Flow>
     */
    private array $flows;

    /**
     * @param array<array-key, WatchedGoal> $watched by flow identifier
     */
    public function __construct(
        private CounterStore $store,
        array $watched,
    ) {
        $goals = [];
        $flows = [];

        foreach ($watched as $id => $goal) {
            $flow = Flow::of((string) $id, $goal['label']);
            $goals[$flow->id] = [SiteId::fromString($goal['site']), $goal['goal']];
            $flows[] = $flow;
        }

        $this->goals = $goals;
        $this->flows = $flows;
    }

    /**
     * @return list<Flow>
     */
    public function flows(): array
    {
        return $this->flows;
    }

    #[Override]
    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array
    {
        if (!isset($this->goals[$flow->id])) {
            throw InvalidValue::because(\sprintf('No goal is watched as the flow "%s".', $flow->id));
        }

        [$site, $goal] = $this->goals[$flow->id];
        $day = UtcDay::of($firstDay);
        $counts = [];

        for ($i = 0; $i < $days; ++$i) {
            $counts[] = $this->store->counts($site, MeasureNames::GOALS, 'goal', $day, $day)[$goal] ?? 0;
            $day = $day->next();
        }

        return $counts;
    }
}
