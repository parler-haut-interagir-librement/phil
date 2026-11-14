<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Preferences;

/**
 * How often a recipient gets the email digest of their unread notifications.
 */
enum DigestFrequency: string
{
    /** No digest: email is sent as each notification comes. */
    case Never = 'never';

    case Daily = 'daily';

    case Weekly = 'weekly';

    /**
     * How far back a digest looks, as a date modifier; none without a digest.
     */
    public function window(): ?string
    {
        return match ($this) {
            self::Never => null,
            self::Daily => '-1 day',
            self::Weekly => '-7 days',
        };
    }
}
