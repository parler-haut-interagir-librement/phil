<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Audit\SecretEventKind;
use Phil\UserSecretsBundle\Application\Contract\RefreshLock;
use Phil\UserSecretsBundle\Application\Contract\TokenRefresher;
use Phil\UserSecretsBundle\Application\Contract\TokenRevoker;
use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\Exception\ExpiredToken;
use Phil\UserSecretsBundle\Application\Exception\RefreshConflict;
use Phil\UserSecretsBundle\Application\Exception\SecretNotFound;
use Phil\UserSecretsBundle\Application\Exception\UnknownKey;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Application\Exception\WrongCredentialKind;
use Phil\UserSecretsBundle\Domain\Contract\Credential;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Exception\RevokedSecret;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretValue;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The secrets people entrust: stored and listed by their owner, read only by a task run for their owner, refreshed
 * when an OAuth token expires, revoked, removed, and erased with the owner's account. Each change is saved, then
 * handed to the audit trail.
 *
 * A refresh runs under the refresh lock when there is one, and its token is saved by a compare-and-swap on the row,
 * so that two processes keep one refreshed token. An OAuth token revoked, removed or erased is revoked at its service
 * by the application's revoker once it is gone locally; a failure there is logged and audited, never thrown.
 */
final readonly class UserSecrets
{
    /**
     * How many times a refreshed token is saved again over a row other processes keep changing.
     */
    public const int REFRESH_ATTEMPTS = 3;

    /**
     * @param iterable<TokenRefresher> $refreshers
     * @param int                      $refreshLeeway seconds before its expiry when a token is already refreshed
     * @param iterable<TokenRevoker>   $revokers
     */
    public function __construct(
        private StoredSecretRepository $secrets,
        private Envelope $envelope,
        private AuditTrail $audit,
        private ClockInterface $clock,
        private iterable $refreshers = [],
        private int $refreshLeeway = 60,
        private iterable $revokers = [],
        private ?RefreshLock $lock = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Stores a value under the name, replacing the previous one, revoked or not.
     */
    public function store(Owner $owner, SecretName $name, ServiceName $service, Credential $credential): void
    {
        $now = $this->clock->now();
        $sealed = $this->envelope->seal($owner, $name, $service, $credential);
        $secret = $this->secrets->get($owner, $name);

        if (!$secret instanceof StoredSecret) {
            $secret = StoredSecret::seal($owner, $name, $service, $credential->kind(), $sealed, $credential->expiresAt(), $now);
        } else {
            $secret->reseal($service, $credential->kind(), $sealed, $credential->expiresAt(), $now);
        }

        $this->secrets->save($secret);
        $this->audit->record(SecretEventKind::Stored, $owner, $name);
    }

    /**
     * @return list<SecretSummary> the owner's secrets, revoked ones included, by name
     */
    public function secrets(Owner $owner): array
    {
        return array_map(SecretSummary::of(...), $this->secrets->ofOwner($owner));
    }

    /**
     * @throws SecretNotFound
     * @throws RevokedSecret
     * @throws UnknownKey
     * @throws UnreadableSecret
     */
    public function reveal(SecretAccess $access, SecretName $name): Credential
    {
        $secret = $this->secret($access->owner, $name);
        $credential = $this->envelope->open($secret);
        $this->used($access, $secret);

        return $credential;
    }

    /**
     * @throws SecretNotFound
     * @throws RevokedSecret
     * @throws UnknownKey
     * @throws UnreadableSecret
     * @throws WrongCredentialKind
     */
    public function apiKey(SecretAccess $access, SecretName $name): ApiKey
    {
        $credential = $this->reveal($access, $name);

        return $credential instanceof ApiKey ? $credential : throw WrongCredentialKind::of($name->value, CredentialKind::ApiKey, $credential->kind());
    }

    /**
     * The OAuth token, refreshed first when it has expired or expires within the leeway — under the refresh lock, by
     * the first process that takes it; the others find the row refreshed.
     *
     * @throws SecretNotFound
     * @throws RevokedSecret
     * @throws UnknownKey
     * @throws UnreadableSecret
     * @throws WrongCredentialKind
     * @throws ExpiredToken        when it has expired and cannot be refreshed
     * @throws RefreshConflict     when the refreshed token could not be saved over other processes' changes
     */
    public function accessToken(SecretAccess $access, SecretName $name): OAuthToken
    {
        $secret = $this->secret($access->owner, $name);
        $token = $this->tokenOf($secret);
        $refreshed = false;

        if ($this->expiring($token)) {
            [$secret, $token, $refreshed] = $this->lock instanceof RefreshLock
                ? $this->lock->synchronized($access->owner, $name, fn (): array => $this->refreshUnlessDone($access->owner, $name))
                : $this->refresh($secret, $token);
        }

        $this->used($access, $secret, $refreshed);

        return $token;
    }

    /**
     * Wipes the value — the row stays, so the owner sees it was revoked —, then revokes an OAuth token at its service.
     *
     * @throws SecretNotFound
     */
    public function revoke(Owner $owner, SecretName $name): void
    {
        $secret = $this->secret($owner, $name);
        $token = $this->tokenToRevoke($secret);
        $secret->revoke($this->clock->now());

        $this->secrets->save($secret);
        $this->audit->record(SecretEventKind::Revoked, $owner, $name);
        $this->revokeAtService($secret, $token);
    }

    /**
     * Removes the row, then revokes an OAuth token at its service.
     *
     * @return bool whether there was a secret to remove
     */
    public function remove(Owner $owner, SecretName $name): bool
    {
        $secret = $this->secrets->get($owner, $name);

        if (!$secret instanceof StoredSecret) {
            return false;
        }

        $token = $this->tokenToRevoke($secret);
        $this->secrets->remove($secret);
        $this->audit->record(SecretEventKind::Removed, $owner, $name);
        $this->revokeAtService($secret, $token);

        return true;
    }

    /**
     * Removes every secret of the owner, when their account is erased, then revokes their OAuth tokens at their
     * services; running it again removes nothing more.
     *
     * @return int how many secrets were removed
     */
    public function eraseOwner(Owner $owner): int
    {
        $secrets = $this->secrets->ofOwner($owner);
        $tokens = array_map($this->tokenToRevoke(...), $secrets);
        $this->secrets->remove(...$secrets);

        foreach ($secrets as $secret) {
            $this->audit->record(SecretEventKind::Erased, $owner, $secret->name());
        }

        foreach ($secrets as $index => $secret) {
            $this->revokeAtService($secret, $tokens[$index]);
        }

        return \count($secrets);
    }

    private function secret(Owner $owner, SecretName $name): StoredSecret
    {
        return $this->secrets->get($owner, $name) ?? throw SecretNotFound::named($name->value);
    }

    /**
     * @throws RevokedSecret
     * @throws UnreadableSecret
     * @throws WrongCredentialKind
     */
    private function tokenOf(StoredSecret $secret): OAuthToken
    {
        $token = $this->envelope->open($secret);

        return $token instanceof OAuthToken ? $token : throw WrongCredentialKind::of($secret->name()->value, CredentialKind::OAuthToken, $token->kind());
    }

    private function expiring(OAuthToken $token): bool
    {
        return $token->expiresWithin($this->clock->now(), $this->refreshLeeway);
    }

    /**
     * Under the lock: the row read again, and refreshed only when no other process did while this one waited.
     *
     * @return array{StoredSecret, OAuthToken, bool}
     */
    private function refreshUnlessDone(Owner $owner, SecretName $name): array
    {
        $secret = $this->secrets->fresh($owner, $name) ?? throw SecretNotFound::named($name->value);
        $token = $this->tokenOf($secret);

        return $this->expiring($token) ? $this->refresh($secret, $token) : [$secret, $token, false];
    }

    /**
     * Refreshes the token at its service and saves it by a compare-and-swap: when another process changed the row
     * meanwhile, its token is kept if it is fresh; otherwise this one is saved over the new row, a few times at most.
     *
     * @return array{StoredSecret, OAuthToken, bool} the row, the token handed out, and whether this process refreshed it
     */
    private function refresh(StoredSecret $secret, OAuthToken $token): array
    {
        $service = $secret->service();
        $refresher = $this->refresherOf($secret, $token);
        $fresh = $token->refreshedBy($refresher->refresh($service, $token));

        for ($attempt = 1;; ++$attempt) {
            $previous = $secret->sealed();
            $secret->reseal($service, $fresh->kind(), $this->envelope->seal($secret->owner(), $secret->name(), $service, $fresh), $fresh->expiresAt, $this->clock->now());

            if ($this->secrets->replace($secret, $previous)) {
                return [$secret, $fresh, true];
            }

            $secret = $this->secrets->fresh($secret->owner(), $secret->name()) ?? throw SecretNotFound::named($secret->name()->value);
            $current = $this->tokenOf($secret);

            if (!$this->expiring($current)) {
                return [$secret, $current, false];
            }

            if ($attempt >= self::REFRESH_ATTEMPTS) {
                throw RefreshConflict::named($secret->name()->value, $attempt);
            }
        }
    }

    /**
     * @throws ExpiredToken when the token has no refresh token or no refresher serves its service
     */
    private function refresherOf(StoredSecret $secret, OAuthToken $token): TokenRefresher
    {
        if ($token->refreshToken instanceof SecretValue) {
            foreach ($this->refreshers as $refresher) {
                if ($refresher->supports($secret->service())) {
                    return $refresher;
                }
            }
        }

        throw ExpiredToken::named($secret->name()->value);
    }

    /**
     * The OAuth token to revoke at its service before the secret goes, when a revoker serves it; a value that does not
     * open is logged and left to expire at the service.
     */
    private function tokenToRevoke(StoredSecret $secret): ?OAuthToken
    {
        if (CredentialKind::OAuthToken !== $secret->kind() || $secret->revokedAt() instanceof DateTimeImmutable || !$this->revokerOf($secret) instanceof TokenRevoker) {
            return null;
        }

        try {
            $token = $this->envelope->open($secret);
        } catch (UnreadableSecret $unreadableSecret) {
            $this->logger?->warning('User secret {secret} of {owner} did not open, so it is not revoked at its service: {reason}', [
                'owner' => $secret->owner()->value,
                'secret' => $secret->name()->value,
                'reason' => $unreadableSecret->getMessage(),
                'exception' => $unreadableSecret,
            ]);

            return null;
        }

        return $token instanceof OAuthToken ? $token : null;
    }

    private function revokerOf(StoredSecret $secret): ?TokenRevoker
    {
        foreach ($this->revokers as $revoker) {
            if ($revoker->supports($secret->service())) {
                return $revoker;
            }
        }

        return null;
    }

    /**
     * Asks the service to revoke the token, which is already gone locally; a failure is logged and audited.
     */
    private function revokeAtService(StoredSecret $secret, ?OAuthToken $token): void
    {
        $revoker = $this->revokerOf($secret);

        if (!$token instanceof OAuthToken || !$revoker instanceof TokenRevoker) {
            return;
        }

        try {
            $revoker->revoke($secret->service(), $token);
        } catch (Throwable $throwable) {
            $this->logger?->warning('User secret {secret} of {owner} was not revoked at the service {service}: {reason}', [
                'owner' => $secret->owner()->value,
                'secret' => $secret->name()->value,
                'service' => $secret->service()->value,
                'reason' => $throwable->getMessage(),
                'exception' => $throwable,
            ]);
            $this->audit->record(SecretEventKind::ServiceRevocationFailed, $secret->owner(), $secret->name());

            return;
        }

        $this->audit->record(SecretEventKind::RevokedAtService, $secret->owner(), $secret->name());
    }

    /**
     * Saves the use, and the refreshed token with it.
     */
    private function used(SecretAccess $access, StoredSecret $secret, bool $refreshed = false): void
    {
        $secret->markUsed($this->clock->now());
        $this->secrets->save($secret);

        if ($refreshed) {
            $this->audit->record(SecretEventKind::Refreshed, $access->owner, $secret->name());
        }

        $this->audit->record(SecretEventKind::Revealed, $access->owner, $secret->name(), $access->purpose);
    }
}
