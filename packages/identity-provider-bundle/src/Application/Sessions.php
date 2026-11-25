<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Application\Contract\LogoutNotifier;
use Phil\IdentityProviderBundle\Application\Contract\Transactions;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Psr\Clock\ClockInterface;

/**
 * The signed-in sessions of an account: opened at each sign-in, seen at most once every `touchInterval` seconds,
 * closed one by one or all at once from another device, and closed with the provider's tokens issued in them: the
 * tokens are revoked, then the session closed, in one transaction. A failure undoes both, or, without a transaction,
 * leaves the session open, so that closing it again finishes the work. Once closed, the clients the tokens were issued
 * to are told, each once, through the `LogoutNotifier`.
 *
 * A session also ends on its own: idle for `idleTimeout` seconds since it was last seen, or `maxAge` seconds after it
 * opened, whatever its activity. It is closed, as from another device, the first time it is touched or listed past
 * either bound — a refresh of the provider's tokens issued in it touches it too —; null leaves that bound out. Since
 * the last sighting is written at most once every `touchInterval` seconds, an idle session may close up to that many
 * seconds early.
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
        private ?Transactions $transactions = null,
        private ?LogoutNotifier $logout = null,
        private ?int $idleTimeout = 7200,
        private ?int $maxAge = 43200,
    ) {
        if (null !== $idleTimeout && $idleTimeout < 1 || null !== $maxAge && $maxAge < 1) {
            throw InvalidValue::because('The idle timeout and the maximum age of a session are null or at least one second.');
        }
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
     * The open session of the identifier, seen now; null when it was closed, never existed, or has just been closed for
     * being idle too long or too old.
     */
    public function touch(string $id, ?string $accountId = null): ?AccountSession
    {
        $session = $this->sessions->get($id);

        if (!$session instanceof AccountSession || $session->isClosed() || (null !== $accountId && $session->accountId() !== $accountId)) {
            return null;
        }

        $now = $this->clock->now();
        $expired = $this->expiry($session, $now);

        if (null !== $expired) {
            $this->closeSession($session, $expired);

            return null;
        }

        if ($session->lastSeenAt()->getTimestamp() + $this->touchInterval <= $now->getTimestamp()) {
            $session->see($now);
            $this->sessions->save($session);
        }

        return $session;
    }

    /**
     * The moment the session ends whatever its activity, `maxAge` seconds after it opened; null without that bound.
     */
    public function endsAt(AccountSession $session): ?DateTimeImmutable
    {
        return null === $this->maxAge ? null : $session->createdAt()->modify(\sprintf('+%d seconds', $this->maxAge));
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
     * The open sessions of the account; those idle too long or too old are closed on the way and left out.
     *
     * @return list<AccountSession>
     */
    public function openFor(Account $account): array
    {
        $now = $this->clock->now();
        $open = [];

        foreach ($this->sessions->openFor($account->id()) as $session) {
            $expired = $this->expiry($session, $now);

            if (null === $expired) {
                $open[] = $session;
            } else {
                $this->closeSession($session, $expired);
            }
        }

        return $open;
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

    /**
     * Why the session must end now — `max_age` or `idle_timeout` —, or null while it may go on.
     */
    private function expiry(AccountSession $session, DateTimeImmutable $now): ?string
    {
        if (null !== $this->maxAge && $session->createdAt()->getTimestamp() + $this->maxAge <= $now->getTimestamp()) {
            return 'max_age';
        }

        if (null !== $this->idleTimeout && $session->lastSeenAt()->getTimestamp() + $this->idleTimeout <= $now->getTimestamp()) {
            return 'idle_timeout';
        }

        return null;
    }

    private function closeSession(AccountSession $session, ?string $reason = null): void
    {
        $now = $this->clock->now();
        $clients = [];
        $close = function () use ($session, $now, &$clients): void {
            foreach ($this->grants?->forSession($session->id()) ?? [] as $token) {
                $clients[] = $token->clientId();
                $token->revoke($now);
                $this->grants?->saveToken($token);
            }

            $session->close($now);
            $this->sessions->save($session);
        };

        if ($this->transactions instanceof Transactions) {
            $this->transactions->run($close);
        } else {
            $close();
        }

        $this->journal->record(IdentityEventType::SessionClosed, $session->accountId(), null === $reason ? ['session' => $session->id()] : ['session' => $session->id(), 'reason' => $reason]);

        if ([] !== $clients) {
            $this->logout?->sessionEnded($session->id(), $session->accountId(), array_values(array_unique($clients)));
        }
    }
}
