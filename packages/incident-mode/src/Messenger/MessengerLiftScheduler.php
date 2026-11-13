<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Messenger;

use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Contract\LiftScheduler;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Sends `LiftIncident` on the bus, delayed until the end of the incident: a transport that supports delays — Doctrine,
 * Redis, AMQP — delivers it then. An extension sends another; the first one, delivered early, finds the incident not
 * yet due and does nothing.
 */
final readonly class MessengerLiftScheduler implements LiftScheduler
{
    public function __construct(
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function schedule(Activation $activation): void
    {
        $delay = $activation->endsAt->getTimestamp() - $this->clock->now()->getTimestamp();

        $this->bus->dispatch(new LiftIncident($activation->id), [new DelayStamp(max(0, $delay) * 1000)]);
    }
}
