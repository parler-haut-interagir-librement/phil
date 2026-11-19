<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A bus that keeps the notifications it is given.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<SendNotification> */
    public array $messages = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($message instanceof SendNotification) {
            $this->messages[] = $message;
        }

        return Envelope::wrap($message, $stamps);
    }
}
