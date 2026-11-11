<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application\Queue;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * The Messenger transports of the application: how many messages each holds, the failed messages of the failure
 * transports, and their retry or removal. A retried message is dispatched again to the transport it failed on, then
 * removed from the failure transport; only a failure transport is read or changed message by message.
 */
final readonly class Queues
{
    /**
     * @param ContainerInterface $receivers         the receivers by transport name
     * @param list<string>       $transports        every transport, in the order of the configuration
     * @param list<string>       $failureTransports the transports failed messages land in
     */
    public function __construct(
        private ContainerInterface $receivers,
        private array $transports,
        private array $failureTransports,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @return list<QueueSummary>
     */
    public function overview(): array
    {
        $summaries = [];

        foreach ($this->transports as $transport) {
            $receiver = $this->receiver($transport);
            $summaries[] = new QueueSummary($transport, $this->isFailureTransport($transport), $receiver instanceof MessageCountAwareInterface ? $receiver->getMessageCount() : null);
        }

        return $summaries;
    }

    /**
     * @return list<string>
     */
    public function failureTransports(): array
    {
        return $this->failureTransports;
    }

    public function isFailureTransport(string $transport): bool
    {
        return \in_array($transport, $this->failureTransports, true);
    }

    /**
     * The first `$limit` failed messages of a failure transport; none for another transport, or one that cannot list.
     *
     * @return list<FailedMessage>
     */
    public function failed(string $transport, int $limit): array
    {
        $receiver = $this->isFailureTransport($transport) ? $this->receiver($transport) : null;

        if (!$receiver instanceof ListableReceiverInterface) {
            return [];
        }

        $messages = [];

        foreach ($receiver->all(max(1, $limit)) as $envelope) {
            $message = FailedMessage::fromEnvelope($envelope);

            if ($message instanceof FailedMessage) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Dispatches a failed message again to the transport it failed on, and removes it from the failure transport;
     * false when it is not there.
     */
    public function retry(string $transport, string $id): bool
    {
        $found = $this->find($transport, $id);

        if (null === $found) {
            return false;
        }

        [$receiver, $envelope] = $found;

        $original = $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName();
        $this->bus->dispatch($envelope->getMessage(), null === $original ? [] : [new TransportNamesStamp([$original])]);
        $receiver->reject($envelope);

        return true;
    }

    /**
     * Removes a failed message from its failure transport; false when it is not there.
     */
    public function remove(string $transport, string $id): bool
    {
        $found = $this->find($transport, $id);

        if (null === $found) {
            return false;
        }

        [$receiver, $envelope] = $found;

        $receiver->reject($envelope);

        return true;
    }

    /**
     * @return array{ListableReceiverInterface, Envelope}|null
     */
    private function find(string $transport, string $id): ?array
    {
        $receiver = $this->isFailureTransport($transport) ? $this->receiver($transport) : null;

        if (!$receiver instanceof ListableReceiverInterface) {
            return null;
        }

        $envelope = $receiver->find($id);

        return $envelope instanceof Envelope ? [$receiver, $envelope] : null;
    }

    private function receiver(string $transport): ?ReceiverInterface
    {
        $receiver = $this->receivers->has($transport) ? $this->receivers->get($transport) : null;

        return $receiver instanceof ReceiverInterface ? $receiver : null;
    }
}
