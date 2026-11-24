<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Fixtures;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * A bus that keeps what it is given, and throws once when told to.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public ?Throwable $failNext = null;

    /** How many messages go through before `failNext` is thrown. */
    public int $failAfter = 0;

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($this->failNext instanceof Throwable && 0 === $this->failAfter--) {
            $failure = $this->failNext;
            $this->failNext = null;

            throw $failure;
        }

        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->messages, static fn (object $message): bool => $message instanceof $class));
    }
}
