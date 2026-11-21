<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use LogicException;
use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * A mail transport that keeps what it is handed, and a renderer that keeps what it renders, for the tests of the
 * mailer: an email is rendered before it reaches the transport.
 */
final class RecordingTransport implements TransportInterface, BodyRendererInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    /** @var list<Message> */
    public array $rendered = [];

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (!\in_array($message, $this->rendered, true)) {
            throw new LogicException('The email reached the transport before it was rendered.');
        }

        $this->sent[] = $message;

        return null;
    }

    #[Override]
    public function render(Message $message): void
    {
        $this->rendered[] = $message;
    }

    #[Override]
    public function __toString(): string
    {
        return 'recording://';
    }
}
