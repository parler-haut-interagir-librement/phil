<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * A transport that neither counts nor lists its messages.
 */
final class PlainReceiver implements ReceiverInterface
{
    #[Override]
    public function get(): iterable
    {
        return [];
    }

    #[Override]
    public function ack(Envelope $envelope): void
    {
    }

    #[Override]
    public function reject(Envelope $envelope): void
    {
    }
}
