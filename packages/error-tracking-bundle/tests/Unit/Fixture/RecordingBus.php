<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RecordingBus implements MessageBusInterface
{
    private ?object $message = null;

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->message = $message;

        return Envelope::wrap($message, $stamps);
    }

    public function message(): SendNotification
    {
        return $this->message instanceof SendNotification ? $this->message : throw new RuntimeException('No notification was dispatched.');
    }
}
