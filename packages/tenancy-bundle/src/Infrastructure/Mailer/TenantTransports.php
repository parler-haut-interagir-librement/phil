<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Mailer;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Mailer\Contract\TenantMailSettings;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Transports;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * The mailer's transports, sending in the active tenant's way: a message sent in a tenant goes through the tenant's
 * transport, by the `X-Transport` header Symfony's transports read, and gets the tenant's sender and reply address.
 * What the message already says wins: a header it carries is never replaced. A message sent in no tenant, or in a
 * tenant without settings, leaves as the application's.
 *
 * It decorates `mailer.transports`, which both the mailer and its Messenger handler use: a message queued in a
 * tenant is sent by the worker in the same tenant, which the tenant's Messenger stamp restores. It holds no state of
 * its own: the tenant is read from the context at each message.
 */
final readonly class TenantTransports implements TransportInterface
{
    public function __construct(
        private TransportInterface $inner,
        private TenantContext $context,
        private TenantMailSettings $settings,
    ) {
    }

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $tenant = $this->context->current();
        $mail = $tenant instanceof TenantId ? $this->settings->for($tenant) : null;

        if ($message instanceof Message && $mail instanceof TenantMail) {
            $headers = $message->getHeaders();

            if (null !== $mail->transport && !$headers->has('X-Transport')) {
                $headers->addTextHeader('X-Transport', $mail->transport);
            }

            if ($mail->from instanceof Address && !$headers->has('From')) {
                $headers->addMailboxListHeader('From', [$mail->from]);
            }

            if ($mail->replyTo instanceof Address && !$headers->has('Reply-To')) {
                $headers->addMailboxListHeader('Reply-To', [$mail->replyTo]);
            }
        }

        return $this->inner->send($message, $envelope);
    }

    /**
     * Stops the connections of the transports between two requests or two messages, as the transports it decorates
     * do: the service keeps the `kernel.reset` tag of `mailer.transports`.
     */
    public function close(): void
    {
        if ($this->inner instanceof Transports || $this->inner instanceof self) {
            $this->inner->close();
        }
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->inner;
    }
}
