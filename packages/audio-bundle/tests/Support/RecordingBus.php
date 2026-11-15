<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Support;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A bus that keeps the messages it is given, sending none.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message);
    }
}
