<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\Status;

/**
 * The state of one check, or of the site as a whole: up, degraded — working, with something to look at — or down.
 */
enum HealthStatus: string
{
    case Up = 'up';
    case Degraded = 'degraded';
    case Down = 'down';

    /**
     * The worse of the two: down over degraded, degraded over up.
     */
    public function worst(self $other): self
    {
        return match (true) {
            self::Down === $this || self::Down === $other => self::Down,
            self::Degraded === $this || self::Degraded === $other => self::Degraded,
            default => self::Up,
        };
    }

    /**
     * Whether the site can take traffic: up or degraded.
     */
    public function isAvailable(): bool
    {
        return self::Down !== $this;
    }
}
