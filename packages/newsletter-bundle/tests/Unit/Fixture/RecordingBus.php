<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A bus that keeps what it is given.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    public array $envelopes = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $this->envelopes[] = $envelope;

        return $envelope;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function messages(string $class): array
    {
        $messages = [];

        foreach ($this->envelopes as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof $class) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
