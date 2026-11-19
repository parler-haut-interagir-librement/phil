<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Monitoring;

use Override;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Whether the store of the errors answers and, when a threshold is set, whether new groups are flooding in. Either is
 * degraded rather than down: the site works without its error tracking. Only counts reach the summary, never what an
 * error says.
 *
 * The check reads the groups seen last, a bounded number of them: a burst of new groups is at the top of that list.
 */
final readonly class ErrorStoreCheck implements HealthCheck
{
    public const string NAME = 'error_tracking';

    /** The groups read at most, which is also the highest threshold. */
    public const int MAX_NEW_GROUPS = 200;

    /**
     * @param int $newGroups the new groups within the window that make the check degraded; 0 to ignore them
     * @param int $window    the length of the window, in seconds
     */
    public function __construct(
        private ErrorGroupReader $reader,
        private ClockInterface $clock,
        private int $newGroups,
        private int $window,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        try {
            $groups = $this->reader->groups(null, 0 === $this->newGroups ? 1 : self::MAX_NEW_GROUPS);
        } catch (Throwable) {
            return CheckOutcome::degraded('The error store does not answer.');
        }

        if (0 === $this->newGroups) {
            return CheckOutcome::up();
        }

        $since = $this->clock->now()->modify(\sprintf('-%d seconds', $this->window));
        $new = \count(array_filter($groups, static fn (ErrorGroup $group): bool => $group->firstSeen >= $since));
        $summary = \sprintf('%d new error groups in %d seconds.', $new, $this->window);

        return $new >= $this->newGroups ? CheckOutcome::degraded($summary) : CheckOutcome::up($summary);
    }
}
