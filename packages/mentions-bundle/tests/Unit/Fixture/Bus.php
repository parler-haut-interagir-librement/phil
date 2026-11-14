<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A message bus that keeps what it is given.
 */
final class Bus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}
