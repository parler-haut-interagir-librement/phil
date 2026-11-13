<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A bus that keeps what it is given.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    public array $envelopes = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $this->envelopes[] = $envelope;

        return $envelope;
    }
}
