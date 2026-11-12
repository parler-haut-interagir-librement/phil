<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts;

use DateTimeImmutable;
use DateTimeZone;
use Phil\BrokenFlowAlerts\Contract\AlertSender;
use Phil\BrokenFlowAlerts\Contract\CompletionHistory;
use Phil\BrokenFlowAlerts\Exception\InvalidFlowValue;
use Psr\Clock\ClockInterface;

/**
 * Reads each flow's history, decides whether it broke or recovered on the last whole day, and sends what changed.
 *
 * **Passive.** It reads counters the application already keeps, and sends no request of its own to the flows it
 * watches.
 *
 * **No memory between runs.** The status of the day before is evaluated again from the history, one day earlier, so
 * that an alert goes out on the day a flow breaks and not on every day it stays broken, without a store of past
 * alerts. A run repeated on the same day sends the same alerts again.
 */
final readonly class BrokenFlowWatch
{
    public function __construct(
        private CompletionHistory $history,
        private AlertSender $sender,
        private ClockInterface $clock,
        private BrokenFlowRule $rule = new BrokenFlowRule(),
    ) {
    }

    /**
     * @param iterable<Flow> $flows
     *
     * @return list<FlowAlert> the alerts sent, in the order of the flows
     */
    public function check(iterable $flows): array
    {
        $today = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        $days = $this->rule->historyDays() + 1;
        $firstDay = $today->modify(\sprintf('-%d days', $days));
        $alerts = [];

        foreach ($flows as $flow) {
            $alert = $this->alertOf($flow, $firstDay, $this->historyOf($flow, $firstDay, $days));

            if ($alert instanceof FlowAlert) {
                $this->sender->send($alert);
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * @param list<int> $history one more day than the rule reads: the day before's evaluation starts one day earlier
     */
    private function alertOf(Flow $flow, DateTimeImmutable $firstDay, array $history): ?FlowAlert
    {
        $previous = $this->rule->evaluate(\array_slice($history, 0, -1));
        $current = $this->rule->evaluate(\array_slice($history, 1));
        $lastDay = $firstDay->modify(\sprintf('+%d days', \count($history) - 1));

        if ($current->broken && !$previous->broken) {
            return $this->alert($flow, FlowAlertKind::Broken, $lastDay, $current);
        }

        // A flow that is no longer broken only because its silence has become its rhythm has not recovered.
        if ($previous->broken && !$current->broken && $history[\count($history) - 1] > 0) {
            return $this->alert($flow, FlowAlertKind::Recovered, $lastDay, $current);
        }

        return null;
    }

    private function alert(Flow $flow, FlowAlertKind $kind, DateTimeImmutable $lastDay, FlowStatus $status): FlowAlert
    {
        return new FlowAlert(
            $flow,
            $kind,
            $lastDay->format('Y-m-d'),
            $lastDay->modify(\sprintf('-%d days', $status->days - 1))->format('Y-m-d'),
            $status->observed,
            $status->expected,
        );
    }

    /**
     * @return list<int>
     */
    private function historyOf(Flow $flow, DateTimeImmutable $firstDay, int $days): array
    {
        $history = $this->history->dailyCompletions($flow, $firstDay, $days);

        if (\count($history) !== $days) {
            throw InvalidFlowValue::because(\sprintf('The history of flow "%s" has %d days; %d were asked for.', $flow->id, \count($history), $days));
        }

        return $history;
    }
}
