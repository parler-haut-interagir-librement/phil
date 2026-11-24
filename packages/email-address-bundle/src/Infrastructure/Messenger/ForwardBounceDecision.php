<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Messenger;

use Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends each bounce decision on through Messenger as a `BounceDecidedMessage`, for a handler in a worker: the
 * fingerprint and the recipient identifier, never the address. A duplicate is never dispatched, so never forwarded.
 */
final readonly class ForwardBounceDecision
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(BounceDecided $event): void
    {
        if (BounceDecision::Duplicate === $event->decision) {
            return;
        }

        $notice = $event->notice;
        $this->bus->dispatch(new BounceDecidedMessage($event->decision->value, $notice->kind->value, $notice->fingerprint?->value, $notice->recipientId, $notice->key()));
    }
}
