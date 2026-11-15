<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Psr\Clock\ClockInterface;

/**
 * The signed-in sessions of an account: opened at each sign-in, seen at most once every `touchInterval` seconds,
 * closed one by one or all at once from another device, and closed with the provider's tokens issued in them.
 *
 * A sign-in from a device the account never used, when it has signed in before, is risky: the session is marked, the
 * event is written, and the account is warned by email.
 */
final readonly class Sessions
{
    public function __construct(
        private AccountSessionRepository $sessions,
        private Journal $journal,
        private ClockInterface $clock,
        private ?AccountMailer $mailer = null,
        private ?GrantRepository $grants = null,
        private int $touchInterval = 300,
    ) {
    }

    /**
     * @param list<string> $methods the methods proven, RFC 8176
     */
    public function open(Account $account, string $device, array $methods): AccountSession
    {
        $risky = $this->sessions->hasAny($account->id()) && !$this->sessions->knowsDevice($account->id(), $device);
        $session = AccountSession::open(Base64Url::encode(random_bytes(32)), $account->id(), $device, $methods, $risky, $this->clock->now());
        $this->sessions->save($session);
        $this->journal->record(IdentityEventType::SessionOpened, $account->id(), ['session' => $session->id(), 'risky' => $risky]);

        if ($risky) {
            $this->journal->record(IdentityEventType::RiskySignIn, $account->id(), ['session' => $session->id()]);
            $this->mailer?->sendRiskySignIn($account, $session);
        }

        return $session;
    }

    /**
     * The open session of the identifier, seen now; null when it was closed or never existed.
     */
    public function touch(string $id, ?string $accountId = null): ?AccountSession
    {
        $session = $this->sessions->get($id);

        if (!$session instanceof AccountSession || $session->isClosed() || (null !== $accountId && $session->accountId() !== $accountId)) {
            return null;
        }

        $now = $this->clock->now();

        if ($session->lastSeenAt()->getTimestamp() + $this->touchInterval <= $now->getTimestamp()) {
            $session->see($now);
            $this->sessions->save($session);
        }

        return $session;
    }

    /**
     * @param list<string> $methods
     */
    public function addMethods(string $id, array $methods): void
    {
        $session = $this->sessions->get($id);

        if ($session instanceof AccountSession && !$session->isClosed()) {
            $session->addMethods($methods);
            $this->sessions->save($session);
        }
    }

    /**
     * @return list<AccountSession>
     */
    public function openFor(Account $account): array
    {
        return $this->sessions->openFor($account->id());
    }

    public function close(Account $account, string $id): bool
    {
        $session = $this->sessions->get($id);

        if (!$session instanceof AccountSession || $session->accountId() !== $account->id() || $session->isClosed()) {
            return false;
        }

        $this->closeSession($session);

        return true;
    }

    /**
     * Closes every open session of the account but the one kept, the current one.
     *
     * @return int the sessions closed
     */
    public function closeAll(Account $account, ?string $except = null): int
    {
        $closed = 0;

        foreach ($this->sessions->openFor($account->id()) as $session) {
            if ($session->id() !== $except) {
                $this->closeSession($session);
                ++$closed;
            }
        }

        return $closed;
    }

    private function closeSession(AccountSession $session): void
    {
        $now = $this->clock->now();
        $session->close($now);
        $this->sessions->save($session);

        foreach ($this->grants?->forSession($session->id()) ?? [] as $token) {
            $token->revoke($now);
            $this->grants?->saveToken($token);
        }

        $this->journal->record(IdentityEventType::SessionClosed, $session->accountId(), ['session' => $session->id()]);
    }
}
