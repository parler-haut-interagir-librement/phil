<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use RuntimeException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Throwable;

/**
 * A transport of Symfony Notifier that keeps what it is given, or throws on command: a refusal of its provider, or
 * any other exception.
 */
final class NotifierTransport implements TransportInterface
{
    /** @var list<MessageInterface> */
    public array $sent = [];

    public ?Throwable $failure = null;

    public static function refusal(): Throwable
    {
        return new class('503 Service Unavailable') extends RuntimeException implements TransportExceptionInterface {
            public function getDebug(): string
            {
                return '';
            }
        };
    }

    #[Override]
    public function send(MessageInterface $message): SentMessage
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        $this->sent[] = $message;

        return new SentMessage($message, 'memory');
    }

    #[Override]
    public function supports(MessageInterface $message): bool
    {
        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return 'memory://';
    }
}
