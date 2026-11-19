# OAuth tokens

## A token

`OAuthToken` keeps the access token, the refresh token when the service issued one, the expiry, and the scopes
granted. Build it from the service's token response:

```php
$token = OAuthToken::of($accessToken, $refreshToken, new DateTimeImmutable('+3600 seconds'), ['repo', 'read:user']);
```

A token without expiry never expires as far as the bundle knows.

## Reading a token

`UserSecrets::accessToken()` returns the token of a secret. When it has expired, or expires within `refresh_leeway`
seconds, the bundle first asks the application's refreshers for a new one: the first refresher that supports the
secret's service renews it, the new token is sealed and saved with the read, and a `refreshed` event goes to the
auditors before the `revealed` one. When the service returns no refresh token, the previous one is kept.

## Concurrent refreshes

Two processes may read the same expired token at once — a worker and a request, two workers. Many services rotate the
refresh token at each use and, when an old refresh token comes back, revoke every token of the grant: two refreshes of
one token could end the owner's connection. The bundle guards against it twice.

- **The refresh lock.** With `symfony/lock` installed and `framework.lock` configured, a refresh runs under a lock of
  that secret, taken from FrameworkBundle's `lock.factory` or the factory `lock_factory` names. The process that takes
  it reads the row again: when another process refreshed the token while it waited, it uses that token and does not
  call the service. The lock lasts at most `refresh_lock_ttl` seconds. Use a store every process shares — Redis, a
  database, `flock` on one host —, not `semaphore` across hosts.
- **The compare-and-swap.** The refreshed token is saved only when the row still holds the sealed value it was read
  with — one conditional `UPDATE`. When another process changed the row meanwhile, the bundle reads it again and uses
  that process's token if it is valid; otherwise it saves its own over the new row, at most
  `UserSecrets::REFRESH_ATTEMPTS` times, then fails with `RefreshConflict`. Without a lock, this keeps one token in the
  store, but both processes called the service.

A token revoked or removed while it was being refreshed stays so: the refresh fails with `RevokedSecret` or
`SecretNotFound`.

## Errors

A token that has expired and cannot be refreshed — it has no refresh token, or no refresher serves its service —
fails with `ExpiredToken`: the owner must connect the service again. Reading an API key as a token, or a token with
`apiKey()`, fails with `WrongCredentialKind`.

## Writing a refresher

A refresher implements `TokenRefresher`; autoconfiguration tags it. It usually posts a `refresh_token` grant to the
service's token endpoint:

```php
use Phil\UserSecretsBundle\Application\Contract\TokenRefresher;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GitHubRefresher implements TokenRefresher
{
    public function __construct(private HttpClientInterface $client, private string $clientId, private string $clientSecret)
    {
    }

    public function supports(ServiceName $service): bool
    {
        return 'github' === $service->value;
    }

    public function refresh(ServiceName $service, OAuthToken $token): OAuthToken
    {
        $data = $this->client->request('POST', 'https://github.com/login/oauth/access_token', [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refreshToken?->reveal(),
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ])->toArray();

        return OAuthToken::of($data['access_token'], $data['refresh_token'] ?? null, new DateTimeImmutable('+'.$data['expires_in'].' seconds'), explode(',', $data['scope'] ?? ''));
    }
}
```

A refresher that throws lets its exception through `accessToken()`; the stored token stays as it was.

## Writing a revoker

A revoker implements `TokenRevoker`; autoconfiguration tags it. The bundle calls it when a token is revoked, removed or
erased with the account, after the token is gone locally. It usually posts the refresh token, when there is one, to the
service's revocation endpoint, as RFC 7009 describes — revoking the refresh token also ends the access tokens it
issued:

```php
use Phil\UserSecretsBundle\Application\Contract\TokenRevoker;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExampleRevoker implements TokenRevoker
{
    public function __construct(private HttpClientInterface $client, private string $clientId, private string $clientSecret)
    {
    }

    public function supports(ServiceName $service): bool
    {
        return 'example' === $service->value;
    }

    public function revoke(ServiceName $service, OAuthToken $token): void
    {
        $refresh = $token->refreshToken;

        $this->client->request('POST', 'https://auth.example.com/oauth/revoke', [
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'body' => null === $refresh
                ? ['token' => $token->accessToken->reveal(), 'token_type_hint' => 'access_token']
                : ['token' => $refresh->reveal(), 'token_type_hint' => 'refresh_token'],
        ])->getContent();   // throws on a 4xx or 5xx answer
    }
}
```

RFC 7009 has the service answer 200 for a token it no longer knows, so revoking twice is harmless. A revoker that throws
is logged and audited, never thrown to the caller: keep the token out of the exception's message, as the bundle logs it.
