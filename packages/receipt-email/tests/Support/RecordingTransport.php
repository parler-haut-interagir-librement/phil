<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * A Mailer transport that keeps what it is given.
 */
final class RecordingTransport implements TransportInterface
{
    /** @var list<Email> */
    private array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): null
    {
        \assert($message instanceof Email);
        $this->sent[] = $message;

        return null;
    }

    /**
     * @return list<Email>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function __toString(): string
    {
        return 'recording://';
    }
}
