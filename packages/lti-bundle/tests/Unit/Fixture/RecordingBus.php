<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A bus that records the messages it takes, and refuses the next `$failures` as a transport that cannot be reached.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public int $failures = 0;

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw new TransportException('The transport cannot be reached.');
        }

        $this->messages[] = $message;

        return new Envelope($message);
    }
}
