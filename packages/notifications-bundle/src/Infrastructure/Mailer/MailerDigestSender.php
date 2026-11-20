<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Mailer;

use Override;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Contract\EmailTrackingConsent;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends the digest through the transports of Symfony Mailer, written by the template
 * `@PhilNotifications/email/digest.html.twig` — or the application's own — and rendered before it leaves, to the
 * address of an `EmailRecipientInterface`; a recipient without one is skipped. The sender is the configured address,
 * or the one the mailer's `headers` give when none is configured.
 *
 * It never goes through `MailerInterface`, which puts a `SendEmailMessage` on the bus when Messenger routes it: the
 * message would wait in the transport with the address and every line of the digest. The digest runs in a worker
 * already, one recipient per message.
 *
 * Each digest carries a `TrackingHeader`, which wins over the mailer's `tracking` option: opens and clicks are not
 * followed, unless the `EmailTrackingConsent` says the recipient agreed to it.
 */
final readonly class MailerDigestSender implements DigestSender
{
    public const string TEMPLATE = '@PhilNotifications/email/digest.html.twig';

    public function __construct(
        private TransportInterface $transport,
        private TranslatorInterface $translator,
        private ?string $from = null,
        private string $template = self::TEMPLATE,
        private ?LoggerInterface $logger = null,
        private ?EmailTrackingConsent $tracking = null,
        private ?BodyRendererInterface $renderer = null,
    ) {
    }

    #[Override]
    public function send(RecipientInterface $contact, DigestFrequency $frequency, array $notifications, ?string $locale): void
    {
        if (!$contact instanceof EmailRecipientInterface || '' === $contact->getEmail()) {
            $this->logger?->notice('A {frequency} digest was not sent: the recipient has no email address.', ['frequency' => $frequency->value]);

            return;
        }

        $email = new TemplatedEmail()
            ->to($contact->getEmail())
            ->subject($this->translator->trans('digest.subject.'.$frequency->value, ['%count%' => \count($notifications)], NotificationType::DOMAIN, $locale))
            ->htmlTemplate($this->template)
            ->locale($locale)
            ->context([
                'frequency' => $frequency->value,
                'notifications' => array_map(static fn (RenderedNotification $n): array => $n->toArray(), $notifications),
            ]);

        $recipient = $notifications[0]->notification->recipient;
        $email->getHeaders()->add(new TrackingHeader(
            $this->tracking?->opens($recipient) ?? false,
            $this->tracking?->clicks($recipient) ?? false,
        ));

        if (null !== $this->from) {
            $email->from($this->from);
        }

        $this->renderer?->render($email);
        $this->transport->send($email);
    }
}
