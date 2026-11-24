<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Support;

use Phil\InventoryAlerts\Contract\SubscriberNotifier;
use Phil\InventoryAlerts\Subscription\Wave;
use RuntimeException;

/**
 * Remembers the waves it is given, or fails when told to.
 */
final class RecordingSubscriberNotifier implements SubscriberNotifier
{
    /** @var list<Wave> */
    public array $waves = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function notify(Wave $wave): void
    {
        if ($this->fail) {
            throw new RuntimeException('The mail server is down.');
        }

        $this->waves[] = $wave;
    }
}
