<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * A mailer that keeps the messages, and fails as many times as it is told first.
 */
final class RecordingMailer implements MailerInterface
{
    /** @var list<Email> */
    public array $sent = [];

    public int $failures = 0;

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw new TransportException('The transport is down.');
        }

        \assert($message instanceof Email);
        $this->sent[] = $message;
    }
}
