<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Messenger;

use Override;
use Phil\ErrorTracking\Contract\GroupAlerter;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the alert rather than sending it in the request that failed: the message carries the fingerprint and the
 * occurrence only.
 */
final readonly class MessengerAlerter implements GroupAlerter
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[Override]
    public function alert(ErrorGroup $group, Occurrence $occurrence): void
    {
        $this->bus->dispatch(new AlertErrorGroup($group->fingerprint, $occurrence->value));
    }
}
