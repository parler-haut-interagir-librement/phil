<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * A Mailer transport that keeps the messages, and fails as many times as it is told first.
 */
final class RecordingMailer implements TransportInterface
{
    /** @var list<Email> */
    public array $sent = [];

    public int $failures = 0;

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw new TransportException('The transport is down.');
        }

        \assert($message instanceof Email);
        $this->sent[] = $message;

        return null;
    }

    #[Override]
    public function __toString(): string
    {
        return 'recording://';
    }
}
