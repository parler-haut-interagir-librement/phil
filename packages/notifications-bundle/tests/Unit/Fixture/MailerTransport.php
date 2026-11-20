<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Throwable;

/**
 * A transport of Symfony Mailer that keeps what it is given, or refuses it on command.
 */
final class MailerTransport implements TransportInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    public ?Throwable $failure = null;

    public static function refusing(): self
    {
        $transport = new self();
        $transport->failure = new TransportException('550 mailbox unavailable');

        return $transport;
    }

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        $this->sent[] = $message;

        return null;
    }

    #[Override]
    public function __toString(): string
    {
        return 'memory://';
    }
}
