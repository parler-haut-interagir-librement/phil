<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * The health of a site or of one of its checks, as its agent reports it; `Unknown` when the site reports none or
 * cannot be reached.
 */
enum HealthState: string
{
    case Up = 'up';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';

    /**
     * The worse of two states: down, then degraded, then unknown, then up.
     */
    public function worst(self $other): self
    {
        return $this->rank() >= $other->rank() ? $this : $other;
    }

    private function rank(): int
    {
        return match ($this) {
            self::Up => 0,
            self::Unknown => 1,
            self::Degraded => 2,
            self::Down => 3,
        };
    }
}
