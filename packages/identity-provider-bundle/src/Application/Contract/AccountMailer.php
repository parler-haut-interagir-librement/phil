<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;

/**
 * The messages an account receives by email: the link that verifies its address, the link that resets its password,
 * the link that signs it in, and the warning of a risky sign-in. The bundle's adapter sends them through the Mailer
 * component with its templates; without a mailer, nothing is sent and the events still are.
 */
interface AccountMailer
{
    public function sendVerification(Account $account, string $url): void;

    public function sendPasswordReset(Account $account, string $url): void;

    public function sendLoginLink(Account $account, string $url): void;

    public function sendRiskySignIn(Account $account, AccountSession $session): void;
}
