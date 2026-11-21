<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Mailer;

use Override;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Contract\EmailTrackingConsent;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Infrastructure\Http\DigestLinks;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends the digest through the transports of Symfony Mailer, written by the templates
 * `@PhilNotifications/email/digest.html.twig` and `digest.txt.twig` — or the application's own — and rendered before
 * it leaves, to the
 * address of an `EmailRecipientInterface`; a recipient without one is skipped. The sender is the configured address,
 * or the one the mailer's `headers` give when none is configured.
 *
 * It never goes through `MailerInterface`, which puts a `SendEmailMessage` on the bus when Messenger routes it: the
 * message would wait in the transport with the address and every line of the digest. The digest runs in a worker
 * already, one recipient per message.
 *
 * Each digest carries a `TrackingHeader`, which wins over the mailer's `tracking` option: opens and clicks are not
 * followed, unless the `EmailTrackingConsent` says the recipient agreed to it.
 *
 * With `DigestLinks`, the templates are given `preferences_url`, the application's page of notification preferences,
 * and `unsubscribe_url`, the signed link that stops the recipient's digest, each null when there is none; the signed
 * link is also the one-click unsubscription of RFC 8058, in `List-Unsubscribe` and
 * `List-Unsubscribe-Post: List-Unsubscribe=One-Click`.
 */
final readonly class MailerDigestSender implements DigestSender
{
    public const string TEMPLATE = '@PhilNotifications/email/digest.html.twig';

    public const string TEXT_TEMPLATE = '@PhilNotifications/email/digest.txt.twig';

    public function __construct(
        private TransportInterface $transport,
        private TranslatorInterface $translator,
        private ?string $from = null,
        private string $template = self::TEMPLATE,
        private ?LoggerInterface $logger = null,
        private ?EmailTrackingConsent $tracking = null,
        private ?BodyRendererInterface $renderer = null,
        private ?DigestLinks $links = null,
        private string $textTemplate = self::TEXT_TEMPLATE,
    ) {
    }

    #[Override]
    public function send(RecipientInterface $contact, DigestFrequency $frequency, array $notifications, ?string $locale): void
    {
        if (!$contact instanceof EmailRecipientInterface || '' === $contact->getEmail()) {
            $this->logger?->notice('A {frequency} digest was not sent: the recipient has no email address.', ['frequency' => $frequency->value]);

            return;
        }

        $recipient = $notifications[0]->notification->recipient;
        $unsubscribe = $this->links?->unsubscribe($recipient);
        $email = new TemplatedEmail()
            ->to($contact->getEmail())
            ->subject($this->translator->trans('digest.subject.'.$frequency->value, ['%count%' => \count($notifications)], NotificationType::DOMAIN, $locale))
            ->htmlTemplate($this->template)
            ->textTemplate($this->textTemplate)
            ->locale($locale)
            ->context([
                'frequency' => $frequency->value,
                'notifications' => array_map(static fn (RenderedNotification $n): array => $n->toArray(), $notifications),
                'preferences_url' => $this->links?->preferences(),
                'unsubscribe_url' => $unsubscribe,
            ]);

        if (null !== $unsubscribe) {
            $email->getHeaders()
                ->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>')
                ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

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
