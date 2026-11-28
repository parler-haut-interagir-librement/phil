<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Support;

use Override;
use Phil\LocalPickup\Contract\ArrivalNotifier;
use Phil\LocalPickup\Contract\ExpiredPickupCanceller;
use Phil\LocalPickup\Contract\ExpiredPickupRestocker;
use Phil\LocalPickup\Contract\ReadyNoticeDispatcher;
use Phil\LocalPickup\Contract\ReadyNotifier;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\ReadyNotice;
use RuntimeException;

/**
 * Every port of the library, recording what it is asked.
 */
final class Recorder implements ReadyNoticeDispatcher, ReadyNotifier, ArrivalNotifier, ExpiredPickupCanceller, ExpiredPickupRestocker
{
    /** @var list<string> */
    public array $dispatched = [];

    /** @var list<ReadyNotice> */
    public array $notices = [];

    /** @var list<string> */
    public array $arrivals = [];

    /** @var list<string> */
    public array $cancelled = [];

    /** @var list<string> */
    public array $restocked = [];

    public bool $failNotice = false;

    #[Override]
    public function dispatch(string $order): void
    {
        $this->dispatched[] = $order;
    }

    #[Override]
    public function notify(ReadyNotice $notice): void
    {
        if ($this->failNotice) {
            throw new RuntimeException('The notice could not be sent.');
        }

        $this->notices[] = $notice;
    }

    #[Override]
    public function arrived(Pickup $pickup): void
    {
        $this->arrivals[] = $pickup->order;
    }

    #[Override]
    public function cancelAndRefund(Pickup $pickup): void
    {
        $this->cancelled[] = $pickup->order;
    }

    #[Override]
    public function restock(Pickup $pickup): void
    {
        $this->restocked[] = $pickup->order.'@'.$pickup->point->stockLocation;
    }
}
