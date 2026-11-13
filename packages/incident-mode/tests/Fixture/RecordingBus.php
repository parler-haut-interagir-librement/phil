<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Keeps every envelope sent, in order, and delivers none.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    public array $sent = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return $this->sent[] = new Envelope($message, $stamps);
    }
}
