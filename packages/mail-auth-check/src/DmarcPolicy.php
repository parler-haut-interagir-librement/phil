<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck;

/**
 * What a DMARC record asks receivers to do with mail that fails it: `none` only monitors, `quarantine` sends it to
 * spam, `reject` refuses it.
 */
enum DmarcPolicy: string
{
    case None = 'none';
    case Quarantine = 'quarantine';
    case Reject = 'reject';

    /**
     * Whether this policy is as strict as the other one, or stricter.
     */
    public function isAtLeast(self $other): bool
    {
        return match ($other) {
            self::None => true,
            self::Quarantine => self::None !== $this,
            self::Reject => self::Reject === $this,
        };
    }
}
