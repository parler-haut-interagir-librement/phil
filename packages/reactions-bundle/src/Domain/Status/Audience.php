<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\Status;

/**
 * Who a part of the reactions is shown to.
 */
enum Audience: string
{
    case Everyone = 'everyone';
    case Members = 'members';
    case Nobody = 'nobody';

    public function admits(bool $isMember): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::Members => $isMember,
            self::Nobody => false,
        };
    }
}
