<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Messenger;

use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\Contract\GroupAlerter;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;

/**
 * Sends a queued alert: reads the group by its fingerprint and hands it to the alerter that sends. A group purged in
 * the meantime, or an occurrence this version does not know, sends nothing.
 */
final readonly class AlertErrorGroupHandler
{
    public function __construct(
        private ErrorStore $store,
        private GroupAlerter $alerter,
    ) {
    }

    public function __invoke(AlertErrorGroup $message): void
    {
        $group = $this->store->find($message->fingerprint);
        $occurrence = Occurrence::tryFrom($message->occurrence);

        if ($group instanceof ErrorGroup && $occurrence instanceof Occurrence) {
            $this->alerter->alert($group, $occurrence);
        }
    }
}
