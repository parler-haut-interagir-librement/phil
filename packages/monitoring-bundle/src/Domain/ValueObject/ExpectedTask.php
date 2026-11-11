<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\Exception\InvalidValue;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;

/**
 * A scheduled task that is expected to beat: its name, how often it runs, and the grace it gets before it counts as
 * late — a nightly task that takes a while, a clock that drifts. A task is late once its last beat is older than the
 * period and the grace together.
 */
final readonly class ExpectedTask
{
    /**
     * @var non-empty-string
     */
    public string $name;

    public function __construct(
        string $name,
        public int $periodSeconds,
        public int $graceSeconds = 0,
    ) {
        $this->name = Name::check($name, 'task');

        if ($periodSeconds < 1) {
            throw InvalidValue::because(\sprintf('The task "%s" runs at least once a second, not every %d seconds.', $name, $periodSeconds));
        }

        if ($graceSeconds < 0) {
            throw InvalidValue::because(\sprintf('The grace of the task "%s" is 0 seconds or more, not %d.', $name, $graceSeconds));
        }
    }

    /**
     * The moment after which the task is late, given its last beat.
     */
    public function dueBy(DateTimeImmutable $lastBeat): DateTimeImmutable
    {
        return $lastBeat->modify(\sprintf('+%d seconds', $this->periodSeconds + $this->graceSeconds));
    }

    /**
     * Where the task stands at `$now`: never beaten, late past its due moment, else on time. Instants are compared as
     * timestamps, never as objects.
     */
    public function stateAt(?DateTimeImmutable $lastBeat, DateTimeImmutable $now): HeartbeatState
    {
        if (!$lastBeat instanceof DateTimeImmutable) {
            return HeartbeatState::Never;
        }

        return $now->getTimestamp() > $this->dueBy($lastBeat)->getTimestamp() ? HeartbeatState::Late : HeartbeatState::OnTime;
    }
}
