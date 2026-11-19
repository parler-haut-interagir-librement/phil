<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Contract;

/**
 * The delay before another confirmation of a double opt-in goes to the same subscriber: a form posted again and again
 * does not send a confirmation each time to an address whose owner never asked for it.
 */
interface ConfirmationThrottle
{
    /**
     * Takes the place of one confirmation to this subscriber and answers `true`; or answers `false` when one was sent
     * within the delay.
     */
    public function allowsConfirmation(string $subscriberId): bool;
}
