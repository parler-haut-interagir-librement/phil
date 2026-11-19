<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Domain\Enum;

/**
 * Where a short link stands at a moment: in force, not yet in force, or no longer — disabled, past its end, or out of
 * visits.
 */
enum LinkState: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Disabled = 'disabled';
    case Expired = 'expired';
    case Exhausted = 'exhausted';

    /**
     * Whether a request for the link is answered with a `410 Gone`: a link that was in force and is no longer.
     */
    public function isGone(): bool
    {
        return \in_array($this, [self::Disabled, self::Expired, self::Exhausted], true);
    }
}
