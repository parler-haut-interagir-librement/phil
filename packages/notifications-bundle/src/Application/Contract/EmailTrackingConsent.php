<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

/**
 * Whether a recipient agreed that the emails sent to them be followed: their opens counted by a pixel, their clicks by
 * redirected links. Without one, neither is followed.
 */
interface EmailTrackingConsent
{
    /**
     * Whether the opens of the emails sent to this recipient may be counted.
     */
    public function opens(string $recipient): bool;

    /**
     * Whether the clicks in the emails sent to this recipient may be followed.
     */
    public function clicks(string $recipient): bool;
}
