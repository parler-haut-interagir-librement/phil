<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Oidc\Scopes;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * The token, revocation, introspection and userinfo endpoints' work.
 *
 * - A client authenticates with its secret — HTTP Basic or the form body — or, public, by its identifier alone, PKCE
 *   standing for the secret.
 * - A code is exchanged once, by its client, with the same redirect address and the PKCE verifier of its challenge
 *   — none when the request carried no challenge, so that a verifier cannot be added after the fact (RFC 9700,
 *   section 2.1.1) —; a second exchange revokes every token issued from it. The code is marked used in one step, so
 *   that of two requests presenting it at once only one gets tokens, the other being refused as a reuse.
 * - The access token is a JSON Web Token (RFC 9068) whose `jti` has a row, so that it can be revoked and introspected;
 *   the ID token carries `auth_time`, `amr`, `nonce`, `sid` and `at_hash`; a refresh token, opaque, is issued for the
 *   scope `offline_access` and rotated at each use, in one step, a rotated one presented again — even by a request
 *   that read it at the same time as the one that rotated it — revoking its whole family.
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
    ) {
    }

    /**
     * @throws OAuthError `invalid_client` when the client is unknown, disabled, or its secret does not match
     */
    public function authenticateClient(string $clientId, #[SensitiveParameter] ?string $secret): Client
    {
        $client = $this->clients->get($clientId);

        if (!$client instanceof Client || $client->isDisabled()) {
            throw OAuthError::invalidClient();
        }

        if ($client->isPublic() ? null !== $secret : (null === $secret || !$client->verifySecret($secret))) {
            throw OAuthError::invalidClient();
        }

        return $client;
    }

    /**
     * @return array<string, int|string>
     *
     * @throws OAuthError
     */
    public function exchangeCode(Client $client, string $code, string $redirectUri, ?string $verifier): array
    {
        $now = $this->clock->now();
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

        return $this->issue($client, $account, $grant->scopes(), $grant->family(), $grant->sessionId(), $grant->authTime(), $grant->methods(), $grant->nonce(), $now);
    }

    /**
     * @return array<string, int|string>
     *
     * @throws OAuthError
     */
    public function refresh(Client $client, #[SensitiveParameter] string $refreshToken, ?string $scope): array
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

        $scopes = $token->scopes();

        if (null !== $scope) {
            $asked = Scopes::split($scope);

            if ([] !== array_diff($asked, $scopes)) {
                throw OAuthError::invalidScope('A refresh cannot widen the scope.');
            }

            $scopes = array_values(array_unique([...$asked, 'offline_access']));
        }

        if (!$this->grants->rotateToken($token, $now)) {
            $this->revokeFamily($token->family(), $token->accountId(), $now);

            throw OAuthError::invalidGrant('The refresh token was already used.');
        }

        $account = $this->activeAccount($token->accountId());

        return $this->issue($client, $account, $scopes, $token->family(), $token->sessionId(), $token->authTime(), $token->methods(), null, $now);
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

        $account = $this->accounts->get($issued->accountId());

        if (!$account instanceof Account || $account->isDisabled()) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'scope' => implode(' ', $issued->scopes()),
            'client_id' => $issued->clientId(),
            'sub' => $issued->accountId(),
            'token_type' => TokenKind::Access === $issued->kind() ? 'Bearer' : 'refresh_token',
            'exp' => $issued->expiresAt()->getTimestamp(),
            'iat' => $issued->issuedAt()->getTimestamp(),
            'iss' => $this->settings->issuer,
        ];
    }

    /**
     * The account and scopes of a bearer access token, for userinfo; refused once its account or its client is disabled.
     *
     * @return array{Account, list<string>}
     *
     * @throws OAuthError `invalid_token`
     */
    public function bearer(#[SensitiveParameter] string $accessToken): array
    {
        $issued = $this->accessToken($accessToken);
        $client = $this->clients->get($issued->clientId());
        $account = $this->accounts->get($issued->accountId());

        if (!$client instanceof Client || $client->isDisabled() || !$account instanceof Account || $account->isDisabled()) {
            throw OAuthError::invalidToken();
        }

        return [$account, $issued->scopes()];
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
     * @param list<string> $scopes
     * @param list<string> $methods
     *
     * @return array<string, int|string>
     */
    private function issue(Client $client, Account $account, array $scopes, string $family, ?string $sessionId, DateTimeImmutable $authTime, array $methods, ?string $nonce, DateTimeImmutable $now): array
    {
        $key = $this->keys->active();
        $jti = Base64Url::encode(random_bytes(24));
        $accessExpires = $now->modify(\sprintf('+%d seconds', $this->settings->accessTokenLifetime));
        $this->grants->saveToken(IssuedToken::issue($jti, TokenKind::Access, $family, $client->id(), $account->id(), $scopes, $sessionId, $authTime, $methods, $now, $accessExpires));

        $accessToken = Jws::encode(array_filter([
            'iss' => $this->settings->issuer,
            'sub' => $account->id(),
            'aud' => $this->settings->issuer,
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
            'sub' => $account->id(),
            'aud' => $client->id(),
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + $this->settings->idTokenLifetime,
            'auth_time' => $authTime->getTimestamp(),
            'nonce' => $nonce,
            'amr' => [] === $methods ? null : $methods,
            'sid' => $sessionId,
            'at_hash' => Base64Url::encode(substr($hash, 0, 16)),
        ], static fn (mixed $value): bool => null !== $value), $key);

        $response = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->settings->accessTokenLifetime,
            'id_token' => $idToken,
            'scope' => implode(' ', $scopes),
        ];

        if (\in_array('offline_access', $scopes, true)) {
            $refresh = Base64Url::encode(random_bytes(32));
            $this->grants->saveToken(IssuedToken::issue(IssuedToken::idOfRefresh($refresh), TokenKind::Refresh, $family, $client->id(), $account->id(), $scopes, $sessionId, $authTime, $methods, $now, $now->modify(\sprintf('+%d seconds', $this->settings->refreshTokenLifetime))));
            $response['refresh_token'] = $refresh;
        }

        $this->journal->record(IdentityEventType::TokensIssued, $account->id(), ['client' => $client->id(), 'family' => $family]);

        return $response;
    }
}
