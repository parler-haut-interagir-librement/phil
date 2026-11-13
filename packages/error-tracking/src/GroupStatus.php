<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * Where a group stands: open, resolved by someone, ignored on purpose, or regressed — seen again on another release
 * after it was resolved.
 */
enum GroupStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
    case Regressed = 'regressed';

    /**
     * Whether the group still asks for someone's attention.
     */
    public function isUnresolved(): bool
    {
        return self::Open === $this || self::Regressed === $this;
    }
}
