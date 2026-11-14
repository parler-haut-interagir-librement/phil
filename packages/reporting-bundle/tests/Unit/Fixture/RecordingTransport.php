<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use PHPUnit\Framework\Assert;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * A mailer transport that keeps the e-mails it is given, each read once as a transport would.
 */
final class RecordingTransport implements TransportInterface
{
    /** @var list<Email> */
    public array $sent = [];

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        Assert::assertInstanceOf(Email::class, $message);
        $message->toString();
        $this->sent[] = $message;

        return null;
    }

    #[Override]
    public function __toString(): string
    {
        return 'recording://';
    }
}
