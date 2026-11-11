<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * A transport in memory that lists, finds and counts its envelopes, and records the limits it was asked for and the
 * envelopes it rejected.
 */
final class FakeReceiver implements ListableReceiverInterface, MessageCountAwareInterface
{
    /**
     * @var list<?int>
     */
    public array $limits = [];

    /**
     * @var list<Envelope>
     */
    public array $rejected = [];

    /**
     * @param list<Envelope> $envelopes
     */
    public function __construct(
        public array $envelopes = [],
    ) {
    }

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
        $this->rejected[] = $envelope;
    }

    #[Override]
    public function all(?int $limit = null): iterable
    {
        $this->limits[] = $limit;

        return \array_slice($this->envelopes, 0, $limit);
    }

    #[Override]
    public function find(mixed $id): ?Envelope
    {
        foreach ($this->envelopes as $envelope) {
            $stamped = $envelope->last(TransportMessageIdStamp::class)?->getId();

            if (\is_scalar($stamped) && $id === (string) $stamped) {
                return $envelope;
            }
        }

        return null;
    }

    #[Override]
    public function getMessageCount(): int
    {
        return \count($this->envelopes);
    }
}
