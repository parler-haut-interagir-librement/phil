<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use DateInterval;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Psr\Clock\ClockInterface;

/**
 * Whether reports are coming faster than the configured threshold: so many within so many seconds. The back office
 * then proposes the incident mode.
 */
final readonly class ReportSurge
{
    public function __construct(
        private ReportRepository $reports,
        private ClockInterface $clock,
        private int $threshold,
        private int $window,
    ) {
    }

    /**
     * @phpstan-impure
     */
    public function count(): int
    {
        return $this->reports->countSince($this->clock->now()->sub(new DateInterval('PT'.$this->window.'S')));
    }

    /**
     * @phpstan-impure
     */
    public function isSurging(): bool
    {
        return $this->count() >= $this->threshold;
    }

    public function threshold(): int
    {
        return $this->threshold;
    }

    public function window(): int
    {
        return $this->window;
    }
}
