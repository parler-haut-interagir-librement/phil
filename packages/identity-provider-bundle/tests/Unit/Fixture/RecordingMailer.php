<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;

/**
 * A mailer that keeps the messages it would send, for the tests.
 */
final class RecordingMailer implements AccountMailer
{
    /** @var list<array{string, string, string}> the message, the account and the link or the device */
    public array $sent = [];

    /**
     * The message sent in that position, three empty strings when none was.
     *
     * @return array{string, string, string}
     */
    public function message(int $position): array
    {
        return $this->sent[$position] ?? ['', '', ''];
    }

    #[Override]
    public function sendVerification(Account $account, string $url): void
    {
        $this->sent[] = ['verify', $account->id(), $url];
    }

    #[Override]
    public function sendPasswordReset(Account $account, string $url): void
    {
        $this->sent[] = ['reset', $account->id(), $url];
    }

    #[Override]
    public function sendLoginLink(Account $account, string $url): void
    {
        $this->sent[] = ['login', $account->id(), $url];
    }

    #[Override]
    public function sendRiskySignIn(Account $account, AccountSession $session): void
    {
        $this->sent[] = ['risky', $account->id(), $session->device()];
    }
}
