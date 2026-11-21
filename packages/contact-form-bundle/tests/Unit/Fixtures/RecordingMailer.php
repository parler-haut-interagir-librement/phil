<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Unit\Fixtures;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * A Mailer transport that keeps what it is given, or refuses it as a transport that is down.
 */
final class RecordingMailer implements TransportInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    public function __construct(
        private readonly bool $down = false,
    ) {
    }

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->down) {
            throw new TransportException('Connection refused.');
        }

        $this->sent[] = $message;

        return null;
    }

    #[Override]
    public function __toString(): string
    {
        return 'recording://';
    }
}
