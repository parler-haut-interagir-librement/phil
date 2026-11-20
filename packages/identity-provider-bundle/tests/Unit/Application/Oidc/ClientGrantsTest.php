<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\ClientAssertions;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * Client assertions (`private_key_jwt`), the `client_credentials` grant, the resource of a token, refresh tokens bound
 * to their session, and the optional claims of the ID token.
 */
#[CoversClass(ClientAssertions::class)]
#[CoversClass(Tokens::class)]
#[CoversClass(ProviderSettings::class)]
#[CoversClass(ClientRegistry::class)]
#[CoversClass(OAuthError::class)]
#[CoversClass(UsedAssertion::class)]
final class ClientGrantsTest extends TestCase
{
    private const string ISSUER = 'https://id.example';

    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private const string API = 'https://api.example/';

    private const string PATH = '/oidc/token';

    private Services $services;

    private InMemoryClientRepository $clients;

    private InMemoryGrantRepository $grants;

    private InMemorySigningKeyRepository $keyStore;

    private InMemoryAccountSessionRepository $sessions;

    private ProviderSettings $settings;

    private PrivateKey $clientKey;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->clients = new InMemoryClientRepository();
        $this->grants = new InMemoryGrantRepository();
        $this->keyStore = new InMemorySigningKeyRepository();
        $this->sessions = new InMemoryAccountSessionRepository();
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, scopes: [...ProviderSettings::SCOPES, 'events:read', 'events:write']);
        $this->clientKey = PrivateKey::generate(SigningAlgorithm::ES256, 'client-key');
    }

    public function testAClientAuthenticatesByAnAssertionOnceForTheIssuerOrTheTokenEndpoint(): void
    {
        $client = $this->keyClient();
        $tokens = $this->tokens();

        self::assertSame($client->id(), $tokens->authenticateClient('machine', $this->assertion(), ClientAuthMethod::PrivateKeyJwt, self::PATH)->id());
        self::assertSame('machine', $tokens->authenticateClient('machine', $this->assertion(['aud' => self::ISSUER.self::PATH]), ClientAuthMethod::PrivateKeyJwt, self::PATH)->id());
        self::assertSame('machine', $tokens->authenticateClient('machine', $this->assertion(['aud' => ['https://other.example', self::ISSUER]]), ClientAuthMethod::PrivateKeyJwt)->id());

        $assertion = $this->assertion(['jti' => 'once']);
        $tokens->authenticateClient('machine', $assertion, ClientAuthMethod::PrivateKeyJwt);
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('machine', $assertion, ClientAuthMethod::PrivateKeyJwt));
        $this->assertOAuthError('invalid_client', fn (): Client => $tokens->authenticateClient('machine', $this->assertion(['aud' => self::ISSUER.self::PATH]), ClientAuthMethod::PrivateKeyJwt));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedAssertions(): iterable
    {
        yield 'another issuer' => [['iss' => 'other']];
        yield 'another subject' => [['sub' => 'other']];
        yield 'no audience' => [['aud' => null]];
        yield 'another audience' => [['aud' => 'https://other.example']];
        yield 'an audience of another type' => [['aud' => 7]];
        yield 'audiences of another type' => [['aud' => [7, 'https://other.example']]];
        yield 'the token endpoint of another origin' => [['aud' => 'https://other.example/oidc/token']];
        yield 'no expiry' => [['exp' => null]];
        yield 'an expiry as text' => [['exp' => '1794650700']];
        yield 'expired past the skew' => [['exp' => 1_794_650_400 - 60]];
        yield 'an expiry too far ahead' => [['exp' => 1_794_650_400 + 301]];
        yield 'issued ahead past the skew' => [['iat' => 1_794_650_400 + 61]];
        yield 'issued at as text' => [['iat' => '1794650400']];
        yield 'not before ahead past the skew' => [['nbf' => 1_794_650_400 + 61]];
        yield 'no jti' => [['jti' => null]];
        yield 'an empty jti' => [['jti' => '']];
        yield 'a jti as a number' => [['jti' => 5]];
        yield 'a jti too long' => [['jti' => str_repeat('j', ClientAssertions::MAX_JTI_LENGTH + 1)]];
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[DataProvider('refusedAssertions')]
    public function testAnAssertionIsRefused(array $claims): void
    {
        $this->keyClient();

        $this->assertOAuthError('invalid_client', fn (): Client => $this->tokens()->authenticateClient('machine', $this->assertion($claims), ClientAuthMethod::PrivateKeyJwt, self::PATH));
        self::assertSame(0, $this->grants->purge(new DateTimeImmutable('+1 year')), 'A refused assertion records nothing.');
    }

    public function testTheLimitsOfAnAssertionAreInclusive(): void
    {
        $this->keyClient();
        $tokens = $this->tokens();
        $now = 1_794_650_400;

        foreach ([['exp' => $now - 59], ['exp' => $now + 300], ['iat' => $now + 60, 'nbf' => $now + 60], ['jti' => str_repeat('j', ClientAssertions::MAX_JTI_LENGTH)]] as $claims) {
            self::assertSame('machine', $tokens->authenticateClient('machine', $this->assertion($claims), ClientAuthMethod::PrivateKeyJwt)->id());
        }
    }

    public function testAnAssertionIsKeptUntilItExpiresTheSkewAdded(): void
    {
        $this->keyClient();
        $this->tokens()->authenticateClient('machine', $this->assertion(['jti' => 'kept', 'exp' => 1_794_650_400 + 100]), ClientAuthMethod::PrivateKeyJwt);

        self::assertSame(0, $this->grants->purge(new DateTimeImmutable('@'.(1_794_650_400 + 160))));
        self::assertSame(1, $this->grants->purge(new DateTimeImmutable('@'.(1_794_650_400 + 161))));
    }

    public function testAnAssertionSignedByAKeyTheClientDidNotRegisterIsRefused(): void
    {
        $this->keyClient();
        $other = PrivateKey::generate(SigningAlgorithm::ES256, 'client-key');
        $unknown = PrivateKey::generate(SigningAlgorithm::ES256, 'unknown');

        $this->assertOAuthError('invalid_client', fn (): Client => $this->tokens()->authenticateClient('machine', Jws::encode($this->claims(), $other), ClientAuthMethod::PrivateKeyJwt));
        $this->assertOAuthError('invalid_client', fn (): Client => $this->tokens()->authenticateClient('machine', Jws::encode($this->claims(), $unknown), ClientAuthMethod::PrivateKeyJwt));
        $this->assertOAuthError('invalid_client', fn (): Client => $this->tokens()->authenticateClient('machine', 'not.a.token', ClientAuthMethod::PrivateKeyJwt));
    }

    public function testEachClientAuthenticatesByItsOwnMethodAmongThoseTheProviderAccepts(): void
    {
        $this->keyClient();
        [, $secret] = $this->registry()->register('app', 'App', ['https://app.example/cb']);
        $this->registry()->register('spa', 'SPA', ['https://app.example/cb'], public: true);
        $tokens = $this->tokens();

        $this->assertOAuthError('invalid_client', fn (): Client => $tokens->authenticateClient('machine', $this->assertion(), ClientAuthMethod::ClientSecretPost));
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('machine', null));
        $this->assertOAuthError('invalid_client', fn (): Client => $tokens->authenticateClient('app', $this->assertion(), ClientAuthMethod::PrivateKeyJwt));
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('spa', null, ClientAuthMethod::PrivateKeyJwt));
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('spa', 'x', ClientAuthMethod::None));
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('app', null, ClientAuthMethod::ClientSecretBasic));
        self::assertSame('app', $tokens->authenticateClient('app', $secret, ClientAuthMethod::ClientSecretBasic)->id());
        self::assertSame('spa', $tokens->authenticateClient('spa', null)->id());

        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, authMethods: [ClientAuthMethod::PrivateKeyJwt, ClientAuthMethod::None]);
        $strict = $this->tokens();
        $this->assertOAuthError('invalid_client', static fn (): Client => $strict->authenticateClient('app', $secret, ClientAuthMethod::ClientSecretBasic));
        $this->assertOAuthError('invalid_client', static fn (): Client => $strict->authenticateClient('app', $secret));
        self::assertSame('machine', $strict->authenticateClient('machine', $this->assertion(), ClientAuthMethod::PrivateKeyJwt)->id());
        self::assertSame('spa', $strict->authenticateClient('spa', null)->id());
    }

    public function testTheAudiencesOfAnAssertion(): void
    {
        self::assertSame([self::ISSUER, self::ISSUER.self::PATH], $this->settings->assertionAudiences(self::PATH));
        self::assertSame([self::ISSUER], $this->settings->assertionAudiences(''));
        self::assertSame([self::ISSUER], $this->settings->assertionAudiences('oidc/token'));
        self::assertSame(['https://id.example:8443/idp', 'https://id.example:8443/idp/oidc/token'], new ProviderSettings('https://id.example:8443/idp')->assertionAudiences('/idp/oidc/token'));
    }

    public function testTheRegistryOfAClientWithKeysAndResources(): void
    {
        [$client, $secret] = $this->registry()->register('machine', 'Machine', [], scopes: ['events:read'], jwks: [$this->clientKey->publicJwk()], resources: [self::API, 'https://api.example/v2/']);

        self::assertNull($secret);
        self::assertFalse($client->isPublic());
        self::assertSame([self::API, 'https://api.example/v2/'], $client->resources());
        self::assertSame([IdentityEventType::ClientRegistered->value, null, ['client' => 'machine', 'public' => false, 'pkce' => true, 'keys' => true]], array_last($this->services->audit->records));

        $next = PrivateKey::generate(SigningAlgorithm::ES256, 'next');
        $this->registry()->replaceJwks($client, [$next->publicJwk()]);
        self::assertSame([IdentityEventType::ClientKeysReplaced->value, null, ['client' => 'machine']], array_last($this->services->audit->records));
        $stored = $this->clients->get('machine');
        self::assertNotNull($stored);
        self::assertNull($stored->publicKey('client-key'));
        self::assertSame($next->publicJwk(), $stored->publicKey('next'));

        $this->assertRefused('client.invalid', fn (): array => $this->registry()->register('spa', 'SPA', ['https://app.example/cb'], public: true, jwks: [$this->clientKey->publicJwk()]));
        $this->assertRefused('client.resource_invalid', fn (): array => $this->registry()->register('bad', 'Bad', [], jwks: [$this->clientKey->publicJwk()], resources: ['http://api.example']));
        $this->assertRefused('client.resource_invalid', fn (): array => $this->registry()->register('bad', 'Bad', [], jwks: [$this->clientKey->publicJwk()], resources: ['https://api.example/#f']));
        $this->assertRefused('client.invalid', fn (): array => $this->registry()->register('bad', 'Bad', [], jwks: [['kty' => 'EC']]));
        $this->assertRefused('client.invalid', fn () => $this->registry()->replaceJwks($stored, []));
        [$app] = $this->registry()->register('app', 'App', ['https://app.example/cb']);
        $this->assertRefused('client.invalid', fn () => $this->registry()->replaceJwks($app, [$next->publicJwk()]));
    }

    public function testTheClientCredentialsGrant(): void
    {
        $client = $this->keyClient();
        $tokens = $this->tokens();
        $response = $tokens->clientCredentials($client, null, null);

        self::assertSame(['access_token', 'token_type', 'expires_in', 'scope'], array_keys($response));
        self::assertSame('events:read events:write', $response['scope']);
        $access = Jws::decode((string) $response['access_token'], $this->keys()->publicKey(...));
        self::assertSame(['iss', 'sub', 'aud', 'client_id', 'scope', 'jti', 'iat', 'exp'], array_keys($access));
        self::assertSame(['machine', self::API, 1_794_651_000], [$access['sub'], $access['aud'], $access['exp']]);
        self::assertIsString($access['jti']);
        self::assertSame(32, \strlen($access['jti']), 'The identifier is 24 random bytes.');
        $stored = $this->grants->token($access['jti']);
        self::assertNotNull($stored);
        self::assertSame(['events:read', 'events:write'], $stored->scopes(), 'The scopes of the token are a list, without the scope every client holds.');
        $header = json_decode(Base64Url::decode(explode('.', (string) $response['access_token'])[0]), true);
        self::assertIsArray($header);
        self::assertSame('at+jwt', $header['typ']);

        $introspection = $tokens->introspect($client, (string) $response['access_token']);
        self::assertSame(['active' => true, 'scope' => 'events:read events:write', 'client_id' => 'machine', 'sub' => 'machine', 'aud' => self::API, 'token_type' => 'Bearer', 'exp' => 1_794_651_000, 'iat' => 1_794_650_400, 'iss' => self::ISSUER], $introspection);
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $response['access_token']));
        self::assertSame([IdentityEventType::TokensIssued->value, null, ['client' => 'machine', 'grant' => 'client_credentials']], array_last($this->services->audit->records));

        self::assertSame('events:read', $tokens->clientCredentials($client, 'events:read', self::API)['scope']);
        $this->assertOAuthError('invalid_scope', static fn (): array => $tokens->clientCredentials($client, 'openid', null));
        $this->assertOAuthError('invalid_scope', static fn (): array => $tokens->clientCredentials($client, 'events:admin', null));
        $this->assertOAuthError('invalid_target', static fn (): array => $tokens->clientCredentials($client, null, 'https://other.example'));

        $tokens->revoke($client, (string) $response['access_token']);
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $response['access_token']));
    }

    public function testTheClientCredentialsGrantKnowsALocalResourceUnderItsCanonicalForm(): void
    {
        $registry = new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal, allowLocalhost: true);
        [$client] = $registry->register('local', 'Local', [], jwks: [$this->clientKey->publicJwk()], resources: ['http://LOCALHOST:8080/api']);

        $response = $this->tokens()->clientCredentials($client, null, 'http://LOCALHOST:8080/api');

        self::assertSame('http://localhost:8080/api', Jws::decode((string) $response['access_token'], $this->keys()->publicKey(...))['aud']);
    }

    public function testASessionBoundSetAfterTheTokenWasIssuedEndsTheRefreshTokenAtTheBound(): void
    {
        $account = $this->services->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $session = $this->session($account);
        $tokens = $this->tokens();
        $early = $tokens->exchangeCode($client, $this->code($account, 'openid offline_access', $session->id()), 'https://app.example/cb', self::VERIFIER);
        $late = $tokens->exchangeCode($client, $this->code($account, 'openid offline_access', $session->id()), 'https://app.example/cb', self::VERIFIER);

        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, sessionMaxAge: 3600);
        $tokens = $this->tokens();
        $this->services->clock->modify('+3599 seconds');
        self::assertArrayHasKey('refresh_token', $tokens->refresh($client, (string) $early['refresh_token'], null));

        $this->services->clock->modify('+1 second');
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $late['refresh_token'], null));
    }

    public function testTheClientRegistryWithoutAJournalReplacesKeysAllTheSame(): void
    {
        [$client] = $this->registry()->register('machine', 'Machine', [], jwks: [$this->clientKey->publicJwk()], resources: [self::API]);
        $next = PrivateKey::generate(SigningAlgorithm::ES256, 'next');

        new ClientRegistry($this->clients, $this->services->clock, $this->settings)->replaceJwks($client, [$next->publicJwk()]);

        self::assertSame($next->publicJwk(), $this->clients->get('machine')?->publicKey('next'));
    }

    public function testTheClientCredentialsGrantWithoutScopes(): void
    {
        [$client] = $this->registry()->register('bare', 'Bare', [], jwks: [$this->clientKey->publicJwk()], resources: [self::API]);
        $response = $this->tokens()->clientCredentials($client, null, null);

        self::assertSame(['access_token', 'token_type', 'expires_in'], array_keys($response));
        self::assertArrayNotHasKey('scope', Jws::decode((string) $response['access_token'], $this->keys()->publicKey(...)));
    }

    public function testTheClientCredentialsGrantNeedsAConfidentialClientWithResources(): void
    {
        [$several] = $this->registry()->register('several', 'Several', [], jwks: [$this->clientKey->publicJwk()], resources: [self::API, 'https://other.example']);
        [$app] = $this->registry()->register('app', 'App', ['https://app.example/cb']);
        [$spa] = $this->registry()->register('spa', 'SPA', ['https://app.example/cb'], public: true);
        $tokens = $this->tokens();

        $this->assertOAuthError('invalid_target', static fn (): array => $tokens->clientCredentials($several, null, null));
        self::assertSame('https://other.example/', Jws::decode((string) $tokens->clientCredentials($several, null, 'https://other.example')['access_token'], $this->keys()->publicKey(...))['aud']);
        self::assertSame(self::API, Jws::decode((string) $tokens->clientCredentials($several, null, 'HTTPS://API.example:443')['access_token'], $this->keys()->publicKey(...))['aud']);
        $this->assertOAuthError('invalid_target', static fn (): array => $tokens->clientCredentials($several, null, 'not an address'));
        $this->assertOAuthError('unauthorized_client', static fn (): array => $tokens->clientCredentials($app, null, null));
        $this->assertOAuthError('unauthorized_client', static fn (): array => $tokens->clientCredentials($spa, null, null));
    }

    public function testACodeExchangedForAResourceReadsNoUserinfo(): void
    {
        [$client, $secret] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access'], resources: [self::API]);
        self::assertNotNull($secret);
        $account = $this->services->account();
        $tokens = $this->tokens();

        $this->assertOAuthError('invalid_target', fn (): array => $tokens->exchangeCode($client, $this->code($account, 'openid'), 'https://app.example/cb', self::VERIFIER, 'https://other.example'));

        $code = $this->code($account, 'openid offline_access', $this->session($account)->id());
        $this->assertOAuthError('invalid_target', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER, 'https://other.example'));
        $response = $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER, self::API);
        self::assertSame(self::API, Jws::decode((string) $response['access_token'], $this->keys()->publicKey(...))['aud']);
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $response['access_token']));
        self::assertSame(self::API, $tokens->introspect($client, (string) $response['access_token'])['aud']);

        $this->assertOAuthError('invalid_target', static fn (): array => $tokens->refresh($client, (string) $response['refresh_token'], null, 'https://other.example'));
        $refreshed = $tokens->refresh($client, (string) $response['refresh_token'], null);
        [$bearer] = $tokens->bearer((string) $refreshed['access_token']);
        self::assertSame($account->id(), $bearer->id());
    }

    public function testARefreshTokenLivesNoLongerThanItsSession(): void
    {
        $account = $this->services->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $session = $this->session($account);
        $tokens = $this->tokens();
        $response = $tokens->exchangeCode($client, $this->code($account, 'openid offline_access', $session->id()), 'https://app.example/cb', self::VERIFIER);
        $refreshed = $tokens->refresh($client, (string) $response['refresh_token'], null);

        $session->close($this->services->clock->now());
        $this->sessions->save($session);

        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $refreshed['refresh_token'], null));
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $refreshed['access_token']), 'The family is revoked.');
        self::assertNotContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
    }

    public function testARefreshTokenOfAForgottenSessionIsRefused(): void
    {
        $account = $this->services->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $tokens = $this->tokens();
        $response = $tokens->exchangeCode($client, $this->code($account, 'openid offline_access', 'unknown-session'), 'https://app.example/cb', self::VERIFIER);

        self::assertArrayNotHasKey('refresh_token', $response, 'No refresh token outlives a session already gone.');
    }

    public function testTheSessionBoundCapsAndEndsTheRefreshTokens(): void
    {
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, sessionMaxAge: 3600);
        $account = $this->services->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $session = $this->session($account);
        $tokens = $this->tokens();
        $this->services->clock->modify('+10 minutes');
        $response = $tokens->exchangeCode($client, $this->code($account, 'openid offline_access', $session->id()), 'https://app.example/cb', self::VERIFIER);

        $introspection = $tokens->introspect($client, (string) $response['refresh_token']);
        self::assertSame($session->createdAt()->getTimestamp() + 3600, $introspection['exp']);

        $this->services->clock->modify('+49 minutes +59 seconds');
        $refreshed = $tokens->refresh($client, (string) $response['refresh_token'], null);
        self::assertSame($session->createdAt()->getTimestamp() + 3600, $tokens->introspect($client, (string) $refreshed['refresh_token'])['exp']);

        $this->services->clock->modify('+1 second');
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $refreshed['refresh_token'], null));
        self::assertArrayNotHasKey('refresh_token', $tokens->exchangeCode($client, $this->code($account, 'openid offline_access', $session->id()), 'https://app.example/cb', self::VERIFIER));
    }

    public function testRefreshTokensOutliveTheSessionWhenTheProviderDoesNotBindThem(): void
    {
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, refreshBoundToSession: false, sessionMaxAge: 60);
        $account = $this->services->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $session = $this->session($account);
        $tokens = $this->tokens();
        $response = $tokens->exchangeCode($client, $this->code($account, 'openid offline_access', $session->id()), 'https://app.example/cb', self::VERIFIER);
        $session->close($this->services->clock->now());
        $this->sessions->save($session);
        $this->services->clock->modify('+2 minutes');

        self::assertSame(1_794_650_400 + 2_592_000, $tokens->introspect($client, (string) $response['refresh_token'])['exp']);
        self::assertArrayHasKey('refresh_token', $tokens->refresh($client, (string) $response['refresh_token'], null));
    }

    public function testTheOptionalClaimsOfTheIdToken(): void
    {
        $account = $this->services->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb']);
        $session = $this->session($account);
        $this->services->clock->modify('+5 minutes');

        $plain = Jws::decode((string) $this->tokens()->exchangeCode($client, $this->code($account, 'openid', $session->id()), 'https://app.example/cb', self::VERIFIER)['id_token'], $this->keys()->publicKey(...));
        self::assertSame([], array_intersect(['acr', 'idp', 'session_start'], array_keys($plain)));

        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, acr: 'urn:example:loa:2', idp: 'https://network.example', sessionStart: true);
        $claims = Jws::decode((string) $this->tokens()->exchangeCode($client, $this->code($account, 'openid', $session->id()), 'https://app.example/cb', self::VERIFIER)['id_token'], $this->keys()->publicKey(...));
        self::assertSame(['urn:example:loa:2', 'https://network.example', $session->createdAt()->getTimestamp()], [$claims['acr'], $claims['idp'], $claims['session_start']]);

        $none = Jws::decode((string) $this->tokens()->exchangeCode($client, $this->code($account, 'openid', null), 'https://app.example/cb', self::VERIFIER)['id_token'], $this->keys()->publicKey(...));
        self::assertArrayNotHasKey('session_start', $none);
        $withoutStore = new Tokens($this->clients, $this->grants, $this->services->accounts, $this->keys(), $this->settings, $this->services->clock, $this->services->journal);
        self::assertArrayNotHasKey('session_start', Jws::decode((string) $withoutStore->exchangeCode($client, $this->code($account, 'openid', $session->id()), 'https://app.example/cb', self::VERIFIER)['id_token'], $this->keys()->publicKey(...)));
    }

    public function testTheSettingsRefuseWhatTheyCannotHonour(): void
    {
        $refusals = [
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, authMethods: []),
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, clockSkew: -1),
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, clockSkew: 301),
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, assertionMaxLifetime: 0),
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, assertionMaxLifetime: 3601),
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, sessionMaxAge: 0),
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, acr: ''),
            static fn (): ProviderSettings => new ProviderSettings(self::ISSUER, idp: ''),
        ];
        $refused = 0;

        foreach ($refusals as $index => $settings) {
            try {
                $settings();
                self::fail('Accepted the settings '.$index);
            } catch (InvalidValue) {
                ++$refused;
            }
        }

        self::assertSame(\count($refusals), $refused);

        $edges = new ProviderSettings(self::ISSUER, clockSkew: 300, assertionMaxLifetime: 3600, sessionMaxAge: 1);
        self::assertSame([300, 3600, 1], [$edges->clockSkew, $edges->assertionMaxLifetime, $edges->sessionMaxAge]);
        self::assertSame([0, 1], [new ProviderSettings(self::ISSUER, clockSkew: 0)->clockSkew, new ProviderSettings(self::ISSUER, assertionMaxLifetime: 1)->assertionMaxLifetime]);
    }

    private function keyClient(): Client
    {
        [$client] = $this->registry()->register('machine', 'Machine', [], scopes: ['events:read', 'events:write'], jwks: [$this->clientKey->publicJwk()], resources: [self::API]);

        return $client;
    }

    /**
     * @param array<string, mixed> $claims merged over valid claims; a null removes one
     */
    private function assertion(array $claims = []): string
    {
        return Jws::encode($this->claims($claims), $this->clientKey);
    }

    /**
     * @param array<string, mixed> $claims
     *
     * @return array<string, mixed>
     */
    private function claims(array $claims = []): array
    {
        $now = $this->services->clock->now()->getTimestamp();

        return array_filter($claims + ['iss' => 'machine', 'sub' => 'machine', 'aud' => self::ISSUER, 'jti' => bin2hex(random_bytes(8)), 'exp' => $now + 120, 'iat' => $now], static fn (mixed $value): bool => null !== $value);
    }

    private function code(Account $account, string $scope, ?string $sessionId = 'sid'): string
    {
        $authorization = new Authorization($this->clients, $this->grants, $this->settings, $this->services->clock, $this->services->journal);
        $request = $authorization->parse(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => $scope, 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']);

        return Query::get($authorization->issueCode($request, $account, $this->services->clock->now(), [], $sessionId), 'code');
    }

    private function session(Account $account): AccountSession
    {
        $session = AccountSession::open(str_repeat('s', 43), $account->id(), 'Firefox', ['pwd'], false, $this->services->clock->now());
        $this->sessions->save($session);

        return $session;
    }

    private function keys(): SigningKeys
    {
        return new SigningKeys($this->keyStore, new PlainSecretCipher(), $this->settings, $this->services->clock, new LockFactory(new InMemoryStore()), $this->services->journal);
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal);
    }

    private function tokens(): Tokens
    {
        return new Tokens($this->clients, $this->grants, $this->services->accounts, $this->keys(), $this->settings, $this->services->clock, $this->services->journal, $this->sessions);
    }

    private function assertOAuthError(string $error, callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted, expected '.$error);
        } catch (OAuthError $oAuthError) {
            self::assertSame($error, $oAuthError->error);
        }
    }

    private function assertRefused(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted, expected '.$reason);
        } catch (Refused $refused) {
            self::assertSame($reason, $refused->reasons[0]);
        }
    }
}
