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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends the digest by Symfony Mailer, written by the template `@PhilNotifications/email/digest.html.twig` — or the
 * application's own —, to the address of an `EmailRecipientInterface`; a recipient without one is skipped. The sender
 * is the configured address, or the mailer's own when none is configured.
 *
 * Each digest carries a `TrackingHeader`, which wins over the mailer's `tracking` option: opens and clicks are not
 * followed, unless the `EmailTrackingConsent` says the recipient agreed to it.
 */
final readonly class MailerDigestSender implements DigestSender
{
    public const string TEMPLATE = '@PhilNotifications/email/digest.html.twig';

    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private ?string $from = null,
        private string $template = self::TEMPLATE,
        private ?LoggerInterface $logger = null,
        private ?EmailTrackingConsent $tracking = null,
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

        $this->mailer->send($email);
    }
}
