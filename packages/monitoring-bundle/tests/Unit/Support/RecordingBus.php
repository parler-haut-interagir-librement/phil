<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A bus that records what it is given.
 */
final class RecordingBus implements MessageBusInterface
{
    /**
     * @var list<Envelope>
     */
    public array $dispatched = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $this->dispatched[] = $envelope;

        return $envelope;
    }
}
