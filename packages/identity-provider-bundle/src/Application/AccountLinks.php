<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Application\Contract\LinkOrigin;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The links sent by email to verify an address and to reset a password: signed by `SignedLinks`, absolute, carrying
 * the account identifier, the expiry and the signature, never the address. A reset asked for an unknown address sends
 * nothing and answers the same, so that the form tells nobody who has an account. Each link is put on the canonical
 * origin by `LinkOrigin`, never on the host the request named.
 */
final readonly class AccountLinks
{
    public const string VERIFY_ROUTE = 'phil_identity_provider_verify_email';

    public const string RESET_ROUTE = 'phil_identity_provider_reset_password';

    public function __construct(
        private SignedLinks $links,
        private UrlGeneratorInterface $urls,
        private AccountRepository $accounts,
        private Journal $journal,
        private ?AccountMailer $mailer = null,
        private ?LinkOrigin $origin = null,
    ) {
    }

    public function verificationUrl(Account $account): string
    {
        return $this->absolute(self::VERIFY_ROUTE, ['account' => $account->id()] + $this->links->sign(SignedLinks::VERIFY, $account));
    }

    public function sendVerification(Account $account): void
    {
        if (!$account->isEmailVerified()) {
            $this->mailer?->sendVerification($account, $this->verificationUrl($account));
        }
    }

    public function requestPasswordReset(string $email): void
    {
        try {
            $account = $this->accounts->findByEmail(Account::normalizeEmail($email));
        } catch (InvalidValue) {
            return;
        }

        if (!$account instanceof Account || $account->isDisabled()) {
            return;
        }

        $url = $this->absolute(self::RESET_ROUTE, ['account' => $account->id()] + $this->links->sign(SignedLinks::RESET, $account));
        $this->mailer?->sendPasswordReset($account, $url);
        $this->journal->record(IdentityEventType::PasswordResetRequested, $account->id());
    }

    /**
     * The account a link of the purpose was signed for, null when it expired, was used, or was never signed.
     */
    public function account(string $purpose, string $accountId, int $expires, string $signature): ?Account
    {
        $account = $this->accounts->get($accountId);

        return $account instanceof Account && !$account->isDisabled() && $this->links->verify($purpose, $account, $expires, $signature) ? $account : null;
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function absolute(string $route, array $parameters): string
    {
        $generate = fn (): string => $this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->origin?->link($generate) ?? $generate();
    }
}
