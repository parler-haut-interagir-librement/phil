<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Mailer;

use Override;
use Phil\PrivacyBundle\Application\Contract\GuestLinkSender;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Sends the link of a request made without an account through the mailer's transports, at once: the secret of the link
 * never sits in a message queue. The text is `@PhilPrivacy/guest/email.txt.twig`, its block `subject` the subject.
 */
final readonly class MailerGuestLinkSender implements GuestLinkSender
{
    public const string TEMPLATE = '@PhilPrivacy/guest/email.txt.twig';

    public function __construct(
        private TransportInterface $transport,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $from,
    ) {
    }

    #[Override]
    public function send(GuestRequest $request, string $token): void
    {
        $context = [
            'kind' => $request->kind->value,
            'link' => $this->urls->generate(PrivacyRoutes::GUEST_CONFIRM, ['id' => $request->id, 'token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            'expires_at' => $request->expiresAt,
        ];
        $template = $this->twig->load(self::TEMPLATE);

        $this->transport->send(new Email()
            ->from(Address::create($this->from))
            ->to(new Address($request->email))
            ->subject(trim($template->renderBlock('subject', $context)))
            ->text($template->render($context)));
    }
}
