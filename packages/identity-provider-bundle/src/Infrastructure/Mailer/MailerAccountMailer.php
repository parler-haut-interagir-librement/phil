<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Mailer;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The messages to an account through the Mailer component, each a `TemplatedEmail` of the bundle's templates
 * `@PhilIdentityProvider/email/<message>.html.twig`, its subject from the translation domain `phil_identity_provider`.
 */
final readonly class MailerAccountMailer implements AccountMailer
{
    public function __construct(
        private MailerInterface $mailer,
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
        $this->mailer->send(new TemplatedEmail()
            ->from($this->from)
            ->to(new Address($account->email()))
            ->subject($this->translator->trans('email.'.$message.'.subject', ['%site%' => $this->siteName], 'phil_identity_provider'))
            ->htmlTemplate('@PhilIdentityProvider/email/'.$message.'.html.twig')
            ->context($context + ['site' => $this->siteName, 'name' => $account->displayName()]));
    }
}
