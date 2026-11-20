<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Mailer;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The messages to an account through the transports of the Mailer component, each a `TemplatedEmail` of the bundle's
 * templates `@PhilIdentityProvider/email/<message>.html.twig` and `<message>.txt.twig` — the text part writes the link
 * in full —, its subject from the translation domain `phil_identity_provider`.
 *
 * Each message is rendered and handed to the transport in the request that asks for it. It never goes through
 * `MailerInterface`, which puts a `SendEmailMessage` on the bus when Messenger routes it, `async` in the recipe of
 * Messenger: the message would wait in the queue — and in the failure transport, and in their backups — with its
 * context, the link of verification, of reset or of sign-in, which is enough to take the account.
 *
 * Each message carries a `TrackingHeader` that turns off the opens and clicks a provider follows: a provider that
 * rewrites the links to count clicks would hold them too.
 */
final readonly class MailerAccountMailer implements AccountMailer
{
    public function __construct(
        private TransportInterface $transport,
        private BodyRendererInterface $renderer,
        private TranslatorInterface $translator,
        private string $from,
        private string $siteName,
    ) {
    }

    #[Override]
    public function sendVerification(Account $account, string $url): void
    {
        $this->send($account, 'verify_email', ['url' => $url]);
    }

    #[Override]
    public function sendPasswordReset(Account $account, string $url): void
    {
        $this->send($account, 'reset_password', ['url' => $url]);
    }

    #[Override]
    public function sendLoginLink(Account $account, string $url): void
    {
        $this->send($account, 'login_link', ['url' => $url]);
    }

    #[Override]
    public function sendRiskySignIn(Account $account, AccountSession $session): void
    {
        $this->send($account, 'risky_sign_in', ['device' => $session->device(), 'at' => $session->createdAt()]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(Account $account, string $message, array $context): void
    {
        $email = new TemplatedEmail()
            ->from($this->from)
            ->to(new Address($account->email()))
            ->subject($this->translator->trans('email.'.$message.'.subject', ['%site%' => $this->siteName], 'phil_identity_provider'))
            ->htmlTemplate('@PhilIdentityProvider/email/'.$message.'.html.twig')
            ->textTemplate('@PhilIdentityProvider/email/'.$message.'.txt.twig')
            ->context($context + ['site' => $this->siteName, 'name' => $account->displayName()]);
        $email->getHeaders()->add(new TrackingHeader(false, false));

        $this->renderer->render($email);
        $this->transport->send($email);
    }
}
