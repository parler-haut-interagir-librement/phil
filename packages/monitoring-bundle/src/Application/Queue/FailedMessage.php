<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application\Queue;

use DateTimeImmutable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * A failed message as the queues page lists it: its id in the failure transport, the class of the message and of the
 * last error, the transport it failed on, its retries and when it last failed. Neither the content of the message nor
 * the text of the error is read: either may carry personal data.
 */
final readonly class FailedMessage
{
    public function __construct(
        public string $id,
        public string $messageClass,
        public ?string $errorClass,
        public ?string $originalTransport,
        public int $retries,
        public ?DateTimeImmutable $failedAt,
    ) {
    }

    /**
     * The failed message of an envelope read from a failure transport; null for an envelope without an id, which
     * could be neither retried nor removed.
     */
    public static function fromEnvelope(Envelope $envelope): ?self
    {
        $id = $envelope->last(TransportMessageIdStamp::class)?->getId();

        if (!\is_scalar($id)) {
            return null;
        }

        $redelivery = $envelope->last(RedeliveryStamp::class)?->getRedeliveredAt();

        return new self(
            (string) $id,
            $envelope->getMessage()::class,
            $envelope->last(ErrorDetailsStamp::class)?->getExceptionClass(),
            $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName(),
            RedeliveryStamp::getRetryCountFromEnvelope($envelope),
            null === $redelivery ? null : DateTimeImmutable::createFromInterface($redelivery),
        );
    }
}
