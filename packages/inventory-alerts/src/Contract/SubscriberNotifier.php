<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Contract;

use Phil\InventoryAlerts\Subscription\Wave;

/**
 * Sends the alerts of a wave to the customers who asked for them, each with its one-click unsubscription link.
 */
interface SubscriberNotifier
{
    /**
     * Sends the wave. A notifier that queues it puts the subscription identifiers in the messages, never an address;
     * the handler reads each subscription back from the store.
     */
    public function notify(Wave $wave): void;
}
