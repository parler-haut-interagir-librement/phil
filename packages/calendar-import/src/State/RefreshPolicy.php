<?php

declare(strict_types=1);

namespace Phil\CalendarImport\State;

use DateTimeImmutable;
use Phil\CalendarImport\Exception\InvalidLimits;

/**
 * When a source is read again: every `interval` seconds after its last check, or, after failures in a row, after
 * `retry` seconds doubled for each failure past the first, `backoff` seconds at most — a source that is down is asked
 * less and less often, never less than once in `backoff`. A source never checked is due.
 */
final readonly class RefreshPolicy
{
    public const int MIN_SECONDS = 60;

    public const int MAX_SECONDS = 7 * 86_400;

    public function __construct(
        public int $interval = 3_600,
        public int $retry = 300,
        public int $backoff = 86_400,
    ) {
        foreach (['The interval of a refresh' => $interval, 'The first retry of a refresh' => $retry, 'The longest retry of a refresh' => $backoff] as $what => $seconds) {
            if ($seconds < self::MIN_SECONDS || $seconds > self::MAX_SECONDS) {
                throw InvalidLimits::outOfRange($what, $seconds, self::MIN_SECONDS, self::MAX_SECONDS);
            }
        }

        if ($backoff < $retry) {
            throw InvalidLimits::outOfRange('The longest retry of a refresh', $backoff, $retry, self::MAX_SECONDS);
        }
    }

    /**
     * When the source is next due, or null when it was never checked.
     */
    public function nextAt(?SourceState $state): ?DateTimeImmutable
    {
        $checkedAt = $state?->checkedAt;

        if (!$state instanceof SourceState || !$checkedAt instanceof DateTimeImmutable) {
            return null;
        }

        $delay = $state->isFailing() ? min($this->backoff, $this->retry * 2 ** min(20, max(0, $state->failures - 1))) : $this->interval;

        return $checkedAt->modify(\sprintf('+%d seconds', $delay));
    }

    public function isDue(?SourceState $state, DateTimeImmutable $now): bool
    {
        $next = $this->nextAt($state);

        return !$next instanceof DateTimeImmutable || $next <= $now;
    }
}
