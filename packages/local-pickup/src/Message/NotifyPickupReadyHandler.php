<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Message;

use DateTimeImmutable;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\LocalPickup\Contract\ReadyNotifier;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\PickupState;
use Phil\LocalPickup\ReadyNotice;

/**
 * Handles a NotifyPickupReady: reads the pickup again, issues its code, keeps the code's digest, and sends the notice.
 *
 * The digest is saved before the notice is sent and the notice is marked sent after: a notice that fails is sent again
 * with a new code, and a message delivered twice finds the notice sent, or the pickup no longer ready, and sends
 * nothing more. A plain invokable, for symfony/messenger or any bus.
 */
final readonly class NotifyPickupReadyHandler
{
    public function __construct(
        private PickupRepository $pickups,
        private PickupCodes $codes,
        private ReadyNotifier $notifier,
    ) {
    }

    public function __invoke(NotifyPickupReady $message): void
    {
        $pickup = $this->pickups->find($message->order);
        $holdUntil = $pickup?->holdUntil;

        if (!$pickup instanceof Pickup || !$holdUntil instanceof DateTimeImmutable || PickupState::Ready !== $pickup->state || $pickup->notified) {
            return;
        }

        $code = $this->codes->issue($pickup->order);
        $pickup->issueCode($code->digest);
        $this->pickups->save($pickup);

        $this->notifier->notify(new ReadyNotice($pickup->order, $pickup->customer, $pickup->point, $pickup->slot, $holdUntil, $code, $pickup->collector));

        $pickup->markNotified();
        $this->pickups->save($pickup);
    }
}
