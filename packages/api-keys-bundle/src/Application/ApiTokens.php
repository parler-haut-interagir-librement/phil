<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\ApiKeysBundle\Application\Exception\InvalidCredentials;
use Phil\ApiKeysBundle\Application\Exception\TokenNotFound;
use Phil\ApiKeysBundle\Application\Exception\UnknownScope;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Event\TokenCreated;
use Phil\ApiKeysBundle\Domain\Event\TokenLeaked;
use Phil\ApiKeysBundle\Domain\Event\TokenRevoked;
use Phil\ApiKeysBundle\Domain\Event\TokenUsed;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Exception\MalformedToken;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SensitiveParameter;

/**
 * Creates, lists, revokes, rotates, purges and verifies access tokens, and revokes those reported leaked. A token acts
 * for a member — an owner given as a string is one — or for another owner type the configuration declares, such as an
 * organisation or an application, within the scopes that type may hold. The text of a token exists only in the
 * `IssuedToken` its creation returns; the store keeps a keyed hash. The events carry identifiers only and go out once
 * the change is saved.
 *
 * It keeps no state between calls: one instance serves every request of a worker.
 */
final readonly class ApiTokens
{
    /** The most tokens `revokeAll()` revokes in one call. */
    public const int REVOKE_ALL_LIMIT = 10_000;

    private OwnerTypes $owners;

    /**
     * @param positive-int     $defaultLifetime the days a token lasts when its creator gives no expiry
     * @param positive-int     $maxLifetime     the most days a token may last
     * @param non-negative-int $useThrottle     the seconds within which a second use of a token is not written
     */
    public function __construct(
        private ApiTokenRepository $tokens,
        private TokenFormat $format,
        private TokenHasher $hasher,
        private ScopeCatalogue $scopes,
        private ClockInterface $clock,
        private int $defaultLifetime = 90,
        private int $maxLifetime = 365,
        private int $useThrottle = 300,
        private ?EventDispatcherInterface $events = null,
        private ?ClientAddressAnonymizer $addresses = null,
        ?OwnerTypes $owners = null,
    ) {
        $this->owners = $owners ?? new OwnerTypes();
    }

    /**
     * A new token for an owner — a member by the identifier their user is loaded by, or a typed owner —, under a name
     * its owner recognises it by.
     *
     * @param array<array-key, string> $scopes
     * @param DateTimeImmutable|null   $expiresAt null for the default lifetime
     *
     * @throws UnknownScope      when a scope is not declared
     * @throws InvalidTokenValue when the owner or the name is out of shape, the owner type is not declared or may not
     *                           hold a scope, or the expiry is not in the future within the longest lifetime
     */
    public function create(TokenOwner|string $owner, string $name, array $scopes, ?DateTimeImmutable $expiresAt = null): IssuedToken
    {
        $owner = TokenOwner::of($owner);
        $scopes = $this->scopes->select($scopes);
        $this->owners->check($owner, $scopes);
        $now = $this->clock->now();
        $longest = $now->modify(\sprintf('+%d days', $this->maxLifetime));
        $expiresAt ??= $now->modify(\sprintf('+%d days', $this->defaultLifetime));

        if ($expiresAt > $longest) {
            throw InvalidTokenValue::because(\sprintf('A token lasts at most %d days.', $this->maxLifetime));
        }

        return $this->issue($owner, $name, $scopes, $now, $expiresAt);
    }

    /**
     * The tokens of one owner, or of every owner, the newest first.
     *
     * @param positive-int $limit
     *
     * @return list<ApiToken>
     *
     * @throws InvalidTokenValue when the owner is out of shape
     */
    public function find(TokenOwner|string|null $owner = null, int $limit = 100): array
    {
        return $this->tokens->find(null === $owner ? null : TokenOwner::of($owner), $limit);
    }

    /**
     * @param TokenOwner|string|null $owner the owner the token must belong to, a string for a member; null for any
     *
     * @throws TokenNotFound     when there is no such token, or it is another owner's
     * @throws InvalidTokenValue when the owner is out of shape
     */
    public function get(string $id, TokenOwner|string|null $owner = null): ApiToken
    {
        $token = $this->tokens->get($id);

        if (!$token instanceof ApiToken || (null !== $owner && !$token->belongsTo(TokenOwner::of($owner)))) {
            throw TokenNotFound::withId($id);
        }

        return $token;
    }

    /**
     * Revokes a token at once; revoking it again changes nothing and announces nothing.
     *
     * @throws TokenNotFound     when there is no such token, or it is another owner's
     * @throws InvalidTokenValue when the owner is out of shape
     */
    public function revoke(string $id, TokenOwner|string|null $owner = null): ApiToken
    {
        $token = $this->get($id, $owner);

        if ($token->revoke($this->clock->now())) {
            $this->tokens->save($token);
            $this->events?->dispatch(new TokenRevoked($token->id()));
        }

        return $token;
    }

    /**
     * Revokes at once every active token of an owner — an organisation that closes, an application that is
     * uninstalled, a member who leaves —, up to `REVOKE_ALL_LIMIT`, each announced by `TokenRevoked`.
     *
     * @return int how many were revoked
     *
     * @throws InvalidTokenValue when the owner is out of shape
     */
    public function revokeAll(TokenOwner|string $owner): int
    {
        $now = $this->clock->now();
        $revoked = array_filter(
            $this->tokens->find(TokenOwner::of($owner), self::REVOKE_ALL_LIMIT),
            static fn (ApiToken $token): bool => $token->revoke($now),
        );

        if ([] !== $revoked) {
            $this->tokens->save(...$revoked);
        }

        foreach ($revoked as $token) {
            $this->events?->dispatch(new TokenRevoked($token->id()));
        }

        return \count($revoked);
    }

    /**
     * A successor with the same owner, name and scopes, lasting as long as the token did, within the longest
     * lifetime. The token is revoked at once, or keeps working for `$grace` seconds while the client switches.
     *
     * @param non-negative-int $grace
     *
     * @throws TokenNotFound     when there is no such token, or it is another owner's
     * @throws InvalidTokenValue when the token is revoked or expired, or the owner is out of shape
     */
    public function rotate(string $id, TokenOwner|string|null $owner = null, int $grace = 0): IssuedToken
    {
        $token = $this->get($id, $owner);
        $now = $this->clock->now();

        if (!$token->isActiveAt($now)) {
            throw InvalidTokenValue::because('A revoked or expired token is not rotated: create another one.');
        }

        $lifetime = min($token->expiresAt()->getTimestamp() - $token->createdAt()->getTimestamp(), $this->maxLifetime * 86_400);
        $successor = $this->issue($token->tokenOwner(), $token->name(), $token->scopes(), $now, $now->modify(\sprintf('+%d seconds', $lifetime)));

        if (0 === $grace) {
            $this->revoke($token->id());
        } else {
            $token->expireBy($now->modify(\sprintf('+%d seconds', $grace)));
            $this->tokens->save($token);
        }

        return $successor;
    }

    /**
     * Removes the tokens that expired or were revoked more than `$retention` days ago.
     *
     * @param non-negative-int $retention
     *
     * @return int how many were removed
     */
    public function purge(int $retention): int
    {
        return $this->tokens->purge($this->clock->now()->modify(\sprintf('-%d days', $retention)));
    }

    /**
     * The token a client presents, checked: its shape and checksum offline, then its row, its secret against the
     * keyed hash in constant time, its revocation and its expiry. The use is written at most once per throttle
     * interval, with the client's address anonymized when an anonymizer is configured.
     *
     * @throws InvalidCredentials when the token opens nothing
     */
    public function verify(#[SensitiveParameter] string $presented, ?string $clientAddress = null): ApiToken
    {
        try {
            $parsed = $this->format->parse($presented);
        } catch (MalformedToken $malformedToken) {
            throw InvalidCredentials::because(CredentialFailure::Malformed, $malformedToken);
        }

        $token = $this->tokens->get($parsed->id);

        // An unknown identifier costs the same hash as a known one.
        if (!$this->hasher->matches($token?->secretHash() ?? '', $parsed->id, $parsed->secret) || !$token instanceof ApiToken) {
            throw InvalidCredentials::because(CredentialFailure::Unknown);
        }

        $now = $this->clock->now();

        if ($token->isRevoked()) {
            throw InvalidCredentials::because(CredentialFailure::Revoked);
        }

        if ($token->hasExpiredAt($now)) {
            throw InvalidCredentials::because(CredentialFailure::Expired);
        }

        if ($token->isUseDue($now, $this->useThrottle)) {
            $token->recordUse($now, null === $clientAddress ? null : $this->addresses?->anonymize($clientAddress));
            $this->tokens->recordUse($token);
            $this->events?->dispatch(new TokenUsed($token->id()));
        }

        return $token;
    }

    /**
     * A token found where it should not be — published in a repository and reported by a secret scanner —: revoked at
     * once when the text is a token of this application, whatever its state, and announced by `TokenRevoked` then
     * `TokenLeaked` when this revokes it. The text is checked as `verify()` checks it, its use is not recorded.
     *
     * @return ApiToken|null the token the text opens, now revoked; null when the text is not a token of this
     *                       application
     */
    public function revokeLeaked(#[SensitiveParameter] string $presented): ?ApiToken
    {
        try {
            $parsed = $this->format->parse($presented);
        } catch (MalformedToken) {
            return null;
        }

        $token = $this->tokens->get($parsed->id);

        if (!$this->hasher->matches($token?->secretHash() ?? '', $parsed->id, $parsed->secret) || !$token instanceof ApiToken) {
            return null;
        }

        if ($token->revoke($this->clock->now())) {
            $this->tokens->save($token);
            $this->events?->dispatch(new TokenRevoked($token->id()));
            $this->events?->dispatch(new TokenLeaked($token->id()));
        }

        return $token;
    }

    /**
     * @param list<string> $scopes
     */
    private function issue(TokenOwner $owner, string $name, array $scopes, DateTimeImmutable $now, DateTimeImmutable $expiresAt): IssuedToken
    {
        $id = $this->format->newId();
        $secret = $this->format->newSecret();
        $token = ApiToken::issue($id, $owner, $name, $scopes, $this->hasher->hash($id, $secret), $now, $expiresAt);
        $this->tokens->save($token);
        $this->events?->dispatch(new TokenCreated($id));

        return new IssuedToken($token, $this->format->format($id, $secret));
    }
}
