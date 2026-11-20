<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Oidc\Scopes;
use Phil\IdentityProviderBundle\Domain\Oidc\SubjectType;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Uid\Ulid;

/**
 * The token, revocation, introspection and userinfo endpoints' work.
 *
 * - A client authenticates with its secret — HTTP Basic or the form body —, with a client assertion signed by one of
 *   its keys (`private_key_jwt`), or, public, by its identifier alone, PKCE standing for the secret; only by a method
 *   the provider accepts, and the one its registration names.
 * - A code is exchanged once, by its client, with the same redirect address and the PKCE verifier of its challenge
 *   — none when the request carried no challenge, so that a verifier cannot be added after the fact (RFC 9700,
 *   section 2.1.1) —; a second exchange revokes every token issued from it. The code is marked used in one step, so
 *   that of two requests presenting it at once only one gets tokens, the other being refused as a reuse.
 * - The access token is a JSON Web Token (RFC 9068) whose `jti` has a row, so that it can be revoked and introspected;
 *   the ID token carries `auth_time`, `amr`, `nonce`, `sid` and `at_hash`; a refresh token, opaque, is issued for the
 *   scope `offline_access` and rotated at each use, in one step, a rotated one presented again — even by a request
 *   that read it at the same time as the one that rotated it — revoking its whole family. A refresh token issued in a
 *   session lives no longer than it: once the session is closed, or older than the session bound, it is refused and
 *   its family revoked.
 * - A confidential client that registered resources asks tokens for itself by the `client_credentials` grant: an
 *   access token for one of its resources, whose `sub` is the client, without an ID token nor a refresh token. Every
 *   grant may name a `resource` (RFC 8707) the client registered: the access token is for it, its `aud`, and no longer
 *   reads userinfo.
 * - Revocation (RFC 7009) answers the same whether the token was known or not; introspection (RFC 7662) answers
 *   `active: false` for any token that is not the asking client's, and for the tokens of a disabled account.
 */
final readonly class Tokens
{
    public function __construct(
        private ClientRepository $clients,
        private GrantRepository $grants,
        private AccountRepository $accounts,
        private SigningKeys $keys,
        private ProviderSettings $settings,
        private ClockInterface $clock,
        private Journal $journal,
        private ?AccountSessionRepository $sessions = null,
    ) {
    }

    /**
     * @param ?string           $secret            the secret, or the client assertion of `private_key_jwt`
     * @param ?ClientAuthMethod $method            how the client authenticates; read from the secret when null: none
     *                                             without, in the form body with
     * @param ?string           $tokenEndpointPath the path of the token endpoint, which a client assertion may name at the
     *                                             issuer's origin
     *
     * @throws OAuthError `invalid_client` when the client is unknown, disabled, uses a method the provider or its
     *                    registration refuses, or its secret or assertion does not prove it
     */
    public function authenticateClient(string $clientId, #[SensitiveParameter] ?string $secret, ?ClientAuthMethod $method = null, ?string $tokenEndpointPath = null): Client
    {
        $method ??= null === $secret ? ClientAuthMethod::None : ClientAuthMethod::ClientSecretPost;
        $client = $this->clients->get($clientId);

        if (!$client instanceof Client || $client->isDisabled() || !\in_array($method, $this->settings->authMethods, true) || !$client->accepts($method) || (ClientAuthMethod::None === $method) !== (null === $secret)) {
            throw OAuthError::invalidClient();
        }

        if ($method->usesSecret() && !$client->verifySecret((string) $secret)) {
            throw OAuthError::invalidClient();
        }

        if (ClientAuthMethod::PrivateKeyJwt === $method) {
            new ClientAssertions($this->grants, $this->settings, $this->clock)->verify($client, (string) $secret, $this->settings->assertionAudiences($tokenEndpointPath ?? ''));
        }

        return $client;
    }

    /**
     * @return array<string, int|string>
     *
     * @throws OAuthError
     */
    public function exchangeCode(Client $client, string $code, string $redirectUri, ?string $verifier, ?string $resource = null): array
    {
        $now = $this->clock->now();
        $audience = $this->audience($client, $resource);
        $grant = $this->grants->code(AuthorizationCode::idOf($code));

        if (!$grant instanceof AuthorizationCode || $grant->clientId() !== $client->id()) {
            throw OAuthError::invalidGrant('The code is unknown.');
        }

        if ($grant->isUsed() || !$this->grants->consumeCode($grant, $now)) {
            $this->revokeFamily($grant->family(), $grant->accountId(), $now);

            throw OAuthError::invalidGrant('The code was already used.');
        }

        if ($grant->isExpired($now) || $grant->redirectUri() !== $redirectUri || !$this->provesPkce($verifier, $grant->codeChallenge())) {
            throw OAuthError::invalidGrant('The code expired, or its redirect address or PKCE verifier does not match.');
        }

        $account = $this->activeAccount($grant->accountId());

        return $this->issue($client, $account, $grant->scopes(), $grant->family(), $grant->sessionId(), $grant->authTime(), $grant->methods(), $grant->nonce(), $now, $audience);
    }

    /**
     * @return array<string, int|string>
     *
     * @throws OAuthError
     */
    public function refresh(Client $client, #[SensitiveParameter] string $refreshToken, ?string $scope, ?string $resource = null): array
    {
        $now = $this->clock->now();
        $token = $this->grants->token(IssuedToken::idOfRefresh($refreshToken));

        if (!$token instanceof IssuedToken || TokenKind::Refresh !== $token->kind() || $token->clientId() !== $client->id()) {
            throw OAuthError::invalidGrant('The refresh token is unknown.');
        }

        if ($token->isRotated() && !$token->isRevoked()) {
            $this->revokeFamily($token->family(), $token->accountId(), $now);

            throw OAuthError::invalidGrant('The refresh token was already used.');
        }

        if (!$token->isActive($now)) {
            throw OAuthError::invalidGrant('The refresh token expired or was revoked.');
        }

        if (!$this->sessionAllowsRefresh($token->sessionId(), $now)) {
            $this->revokeFamily($token->family(), null, $now);

            throw OAuthError::invalidGrant('The session of the refresh token ended.');
        }

        $audience = $this->audience($client, $resource);
        $scopes = $token->scopes();

        if (null !== $scope) {
            $asked = Scopes::split($scope);

            if ([] !== array_diff($asked, $scopes)) {
                throw OAuthError::invalidScope('A refresh cannot widen the scope.');
            }

            // `openid` stays: a narrowed refresh still issues an ID token, and its access token still reads userinfo.
            $scopes = array_values(array_unique([...$asked, ...array_intersect($scopes, ['openid']), 'offline_access']));
        }

        if (!$this->grants->rotateToken($token, $now)) {
            $this->revokeFamily($token->family(), $token->accountId(), $now);

            throw OAuthError::invalidGrant('The refresh token was already used.');
        }

        $account = $this->activeAccount((string) $token->accountId());

        return $this->issue($client, $account, $scopes, $token->family(), $token->sessionId(), $token->authTime(), $token->methods(), null, $now, $audience);
    }

    /**
     * An access token the client asks for itself (RFC 6749, section 4.4), for one of the resources it registered: the
     * one it names, or its only one. The scopes it asks are among its own, those about an account left out.
     *
     * @return array<string, int|string>
     *
     * @throws OAuthError `unauthorized_client` for a public client or one without resources, `invalid_target` for a
     *                    resource it did not register or a missing one among several, `invalid_scope`
     */
    public function clientCredentials(Client $client, ?string $scope, ?string $resource): array
    {
        if ($client->isPublic() || [] === $client->resources()) {
            throw OAuthError::unauthorizedClient('The client asks tokens for itself only once it registered resources.');
        }

        $resources = $client->resources();
        $audience = $this->audience($client, $resource) ?? (1 === \count($resources) ? $resources[0] : throw OAuthError::invalidTarget('The client names one of its resources.'));

        $allowed = array_values(array_diff($client->scopes(), ProviderSettings::ACCOUNT_SCOPES));
        $scopes = null === $scope ? $allowed : Scopes::split($scope);

        if ([] !== array_diff($scopes, $allowed)) {
            throw OAuthError::invalidScope('A token of the client carries only the scopes of the client, none about an account.');
        }

        $now = $this->clock->now();
        $jti = Base64Url::encode(random_bytes(24));
        $expires = $now->modify(\sprintf('+%d seconds', $this->settings->accessTokenLifetime));
        $this->grants->saveToken(IssuedToken::issue($jti, TokenKind::Access, new Ulid()->toBase32(), $client->id(), null, $scopes, null, $now, [], $now, $expires, $audience));

        $accessToken = Jws::encode(array_filter([
            'iss' => $this->settings->issuer,
            'sub' => $client->id(),
            'aud' => $audience,
            'client_id' => $client->id(),
            'scope' => implode(' ', $scopes),
            'jti' => $jti,
            'iat' => $now->getTimestamp(),
            'exp' => $expires->getTimestamp(),
        ], static fn (int|string $value): bool => '' !== $value), $this->keys->active(), ['typ' => 'at+jwt']);

        $this->journal->record(IdentityEventType::TokensIssued, null, ['client' => $client->id(), 'grant' => 'client_credentials']);

        return array_filter([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->settings->accessTokenLifetime,
            'scope' => implode(' ', $scopes),
        ], static fn (int|string $value): bool => '' !== $value);
    }

    /**
     * Revokes a token of the client, access or refresh; a refresh token takes its family with it.
     */
    public function revoke(Client $client, #[SensitiveParameter] string $token): void
    {
        $issued = $this->find($token);

        if (!$issued instanceof IssuedToken || $issued->clientId() !== $client->id()) {
            return;
        }

        $now = $this->clock->now();

        if (TokenKind::Refresh === $issued->kind()) {
            $this->revokeFamily($issued->family(), null, $now);
        } else {
            $issued->revoke($now);
            $this->grants->saveToken($issued);
        }

        $this->journal->record(IdentityEventType::TokenRevoked, $issued->accountId(), ['client' => $client->id(), 'kind' => $issued->kind()->value]);
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function introspect(Client $client, #[SensitiveParameter] string $token): array
    {
        $issued = $this->find($token);
        $now = $this->clock->now();

        if (!$issued instanceof IssuedToken || $issued->clientId() !== $client->id() || !$issued->isActive($now)) {
            return ['active' => false];
        }

        $accountId = $issued->accountId();
        $account = null === $accountId ? null : $this->accounts->get($accountId);

        if (null !== $accountId && (!$account instanceof Account || $account->isDisabled())) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'scope' => implode(' ', $issued->scopes()),
            'client_id' => $issued->clientId(),
            'sub' => null === $accountId ? $issued->clientId() : new Subjects($this->settings)->of($accountId, $client),
            'aud' => $issued->audience() ?? $this->settings->issuer,
            'token_type' => TokenKind::Access === $issued->kind() ? 'Bearer' : 'refresh_token',
            'exp' => $issued->expiresAt()->getTimestamp(),
            'iat' => $issued->issuedAt()->getTimestamp(),
            'iss' => $this->settings->issuer,
        ];
    }

    /**
     * The account and scopes of a bearer access token, for userinfo; refused once its account or its client is disabled,
     * and for a token of another resource or of the `client_credentials` grant.
     *
     * @return array{Account, list<string>, string} the account, the scopes and the client of the token
     *
     * @throws OAuthError `invalid_token`
     */
    public function bearer(#[SensitiveParameter] string $accessToken): array
    {
        $issued = $this->accessToken($accessToken);
        $accountId = $issued->accountId();

        if (null === $accountId || null !== $issued->audience()) {
            throw OAuthError::invalidToken();
        }

        $client = $this->clients->get($issued->clientId());
        $account = $this->accounts->get($accountId);

        if (!$client instanceof Client || $client->isDisabled() || !$account instanceof Account || $account->isDisabled()) {
            throw OAuthError::invalidToken();
        }

        return [$account, $issued->scopes(), $issued->clientId()];
    }

    /**
     * The `sub` of the account for the client, by the subject type of the provider; null when the client is unknown and
     * the subject depends on it.
     */
    public function subject(string $accountId, ?string $clientId): ?string
    {
        $client = null === $clientId ? null : $this->clients->get($clientId);

        return match (true) {
            $client instanceof Client => new Subjects($this->settings)->of($accountId, $client),
            SubjectType::Account === $this->settings->subjectType => $accountId,
            default => null,
        };
    }

    /**
     * The claims of an ID token the provider issued, its signature and issuer checked, its expiry not: an
     * `id_token_hint` names who is signing out, and may be old (OpenID Connect RP-Initiated Logout, section 2).
     *
     * @return array<string, mixed>|null
     */
    public function idTokenHint(string $idToken): ?array
    {
        try {
            $claims = Jws::decode($idToken, $this->keys->publicKey(...));
        } catch (MalformedInput|VerificationFailed) {
            $claims = [];
        }

        return ($claims['iss'] ?? null) === $this->settings->issuer ? $claims : null;
    }

    private function accessToken(string $accessToken): IssuedToken
    {
        try {
            $claims = Jws::decode($accessToken, $this->keys->publicKey(...));
        } catch (MalformedInput|VerificationFailed) {
            $claims = [];
        }

        $issued = \is_string($claims['jti'] ?? null) ? $this->grants->token($claims['jti']) : null;

        if (!$issued instanceof IssuedToken || TokenKind::Access !== $issued->kind() || ($claims['iss'] ?? null) !== $this->settings->issuer || !$issued->isActive($this->clock->now())) {
            throw OAuthError::invalidToken();
        }

        return $issued;
    }

    /**
     * The row of a token, an access token by its `jti` once its signature verifies, a refresh token by its hash.
     */
    private function find(string $token): ?IssuedToken
    {
        if (2 === substr_count($token, '.')) {
            try {
                $claims = Jws::decode($token, $this->keys->publicKey(...));
            } catch (MalformedInput|VerificationFailed) {
                $claims = [];
            }

            return \is_string($claims['jti'] ?? null) ? $this->grants->token($claims['jti']) : null;
        }

        return $this->grants->token(IssuedToken::idOfRefresh($token));
    }

    /**
     * Whether the verifier answers the challenge of the code: both present and matching, or both absent.
     */
    private function provesPkce(?string $verifier, ?string $challenge): bool
    {
        return null === $challenge ? null === $verifier : null !== $verifier && Pkce::verify($verifier, $challenge);
    }

    /**
     * The audience of an access token: the resource the client named, in its canonical form, which it must have
     * registered; null for the provider.
     *
     * @throws OAuthError `invalid_target`
     */
    private function audience(Client $client, ?string $resource): ?string
    {
        if (null === $resource) {
            return null;
        }

        try {
            $canonical = Url::fromString($resource, true)->toString();
        } catch (InvalidUrl) {
            $canonical = $resource;
        }

        return $client->allowsResource($canonical) ? $canonical : throw OAuthError::invalidTarget('The resource is not one the client registered.');
    }

    /**
     * Whether a refresh token of the session may still be used: no session, or the provider does not bind them; else an
     * open session younger than the session bound.
     */
    private function sessionAllowsRefresh(?string $sessionId, DateTimeImmutable $now): bool
    {
        $until = $this->refreshBound($sessionId);

        return !$until instanceof DateTimeImmutable || $until > $now;
    }

    /**
     * The moment past which a refresh token of the session is refused: now when the session is closed or gone, the
     * session bound after it opened, or null for none.
     */
    private function refreshBound(?string $sessionId): ?DateTimeImmutable
    {
        if (!$this->settings->refreshBoundToSession || null === $sessionId || !$this->sessions instanceof AccountSessionRepository) {
            return null;
        }

        $session = $this->sessions->get($sessionId);

        if (!$session instanceof AccountSession || $session->isClosed()) {
            return new DateTimeImmutable('@0');
        }

        return null === $this->settings->sessionMaxAge ? null : $session->createdAt()->modify(\sprintf('+%d seconds', $this->settings->sessionMaxAge));
    }

    private function activeAccount(string $accountId): Account
    {
        $account = $this->accounts->get($accountId);

        if (!$account instanceof Account || $account->isDisabled()) {
            throw OAuthError::invalidGrant('The account is disabled.');
        }

        return $account;
    }

    private function revokeFamily(string $family, ?string $accountId, DateTimeImmutable $now): void
    {
        foreach ($this->grants->family($family) as $token) {
            $token->revoke($now);
            $this->grants->saveToken($token);
        }

        if (null !== $accountId) {
            $this->journal->record(IdentityEventType::TokenReuseDetected, $accountId, ['family' => $family]);
        }
    }

    /**
     * When the session opened, for `session_start`; null without a session, or one the provider no longer knows.
     */
    private function sessionStart(?string $sessionId): ?int
    {
        $session = null === $sessionId ? null : $this->sessions?->get($sessionId);

        return $session instanceof AccountSession ? $session->createdAt()->getTimestamp() : null;
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $methods
     *
     * @return array<string, int|string>
     */
    private function issue(Client $client, Account $account, array $scopes, string $family, ?string $sessionId, DateTimeImmutable $authTime, array $methods, ?string $nonce, DateTimeImmutable $now, ?string $audience = null): array
    {
        $key = $this->keys->active();
        $jti = Base64Url::encode(random_bytes(24));
        $accessExpires = $now->modify(\sprintf('+%d seconds', $this->settings->accessTokenLifetime));
        $this->grants->saveToken(IssuedToken::issue($jti, TokenKind::Access, $family, $client->id(), $account->id(), $scopes, $sessionId, $authTime, $methods, $now, $accessExpires, $audience));

        $subject = new Subjects($this->settings)->of($account->id(), $client);
        $accessToken = Jws::encode(array_filter([
            'iss' => $this->settings->issuer,
            'sub' => $subject,
            'aud' => $audience ?? $this->settings->issuer,
            'client_id' => $client->id(),
            'scope' => implode(' ', $scopes),
            'jti' => $jti,
            'iat' => $now->getTimestamp(),
            'exp' => $accessExpires->getTimestamp(),
            'auth_time' => $authTime->getTimestamp(),
        ], static fn (int|string $value): bool => '' !== $value), $key, ['typ' => 'at+jwt']);

        $hash = hash('sha256', $accessToken, true);
        $idToken = Jws::encode(array_filter([
            'iss' => $this->settings->issuer,
            'sub' => $subject,
            'aud' => $client->id(),
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + $this->settings->idTokenLifetime,
            'auth_time' => $authTime->getTimestamp(),
            'nonce' => $nonce,
            'amr' => [] === $methods ? null : $methods,
            'sid' => $sessionId,
            'at_hash' => Base64Url::encode(substr($hash, 0, 16)),
            'acr' => $this->settings->acr,
            'idp' => $this->settings->idp,
            'session_start' => $this->settings->sessionStart ? $this->sessionStart($sessionId) : null,
        ], static fn (mixed $value): bool => null !== $value), $key);

        $response = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->settings->accessTokenLifetime,
            'id_token' => $idToken,
            'scope' => implode(' ', $scopes),
        ];

        $refreshExpires = $now->modify(\sprintf('+%d seconds', $this->settings->refreshTokenLifetime));
        $bound = $this->refreshBound($sessionId);

        if ($bound instanceof DateTimeImmutable) {
            $refreshExpires = min($refreshExpires, $bound);
        }

        if (\in_array('offline_access', $scopes, true) && $refreshExpires > $now) {
            $refresh = Base64Url::encode(random_bytes(32));
            $this->grants->saveToken(IssuedToken::issue(IssuedToken::idOfRefresh($refresh), TokenKind::Refresh, $family, $client->id(), $account->id(), $scopes, $sessionId, $authTime, $methods, $now, $refreshExpires, $audience));
            $response['refresh_token'] = $refresh;
        }

        $this->journal->record(IdentityEventType::TokensIssued, $account->id(), ['client' => $client->id(), 'family' => $family]);

        return $response;
    }
}
