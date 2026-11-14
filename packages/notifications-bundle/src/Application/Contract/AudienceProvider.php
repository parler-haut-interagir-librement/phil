<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

/**
 * Turns an audience — a name the application gives a group of recipients, such as `segment:42` or `event:7:attendees`
 * — into the identifiers of its recipients, when a notification sent to it is delivered.
 */
interface AudienceProvider
{
    public function supports(string $audience): bool;

    /**
     * @return iterable<string>
     */
    public function recipients(string $audience): iterable;
}
