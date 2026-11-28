<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The messages dispatched, in order, none handled.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $sent = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->sent[] = $message;

        return new Envelope($message, $stamps);
    }
}
