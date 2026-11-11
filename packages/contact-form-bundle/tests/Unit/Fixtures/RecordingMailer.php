<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Unit\Fixtures;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * A mailer that keeps what it is given, or refuses it as a transport that is down.
 */
final class RecordingMailer implements MailerInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    public function __construct(
        private readonly bool $down = false,
    ) {
    }

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->down) {
            throw new TransportException('Connection refused.');
        }

        $this->sent[] = $message;
    }
}
