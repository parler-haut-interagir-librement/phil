<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\Claims;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\AuthorizationError;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(ProviderSettings::class)]
#[CoversClass(SigningKeys::class)]
#[CoversClass(ClientRegistry::class)]
#[CoversClass(Authorization::class)]
#[CoversClass(Tokens::class)]
#[CoversClass(Claims::class)]
#[CoversClass(OAuthError::class)]
final class ProviderTest extends TestCase
{
    private const string ISSUER = 'https://id.example';

    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private Services $services;

    private InMemoryClientRepository $clients;

    private InMemoryGrantRepository $grants;

    private InMemorySigningKeyRepository $keyStore;

    private ProviderSettings $settings;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->clients = new InMemoryClientRepository();
        $this->grants = new InMemoryGrantRepository();
        $this->keyStore = new InMemorySigningKeyRepository();
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, keyActivationDelay: 100, retiredKeyRetention: 200);
    }

    public function testTheSettings(): void
    {
        $settings = new ProviderSettings('https://id.example');
        self::assertSame([SigningAlgorithm::RS256, 60, 600, 600, 2_592_000, ProviderSettings::SCOPES, 86_400, 604_800], [$settings->algorithm, $settings->codeLifetime, $settings->accessTokenLifetime, $settings->idTokenLifetime, $settings->refreshTokenLifetime, $settings->scopes, $settings->keyActivationDelay, $settings->retiredKeyRetention]);
        new ProviderSettings('https://id.example', codeLifetime: 600, accessTokenLifetime: 1);

        foreach ([
            static fn (): ProviderSettings => new ProviderSettings(''),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example/'),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example?a'),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example#a'),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example', codeLifetime: 601),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example', codeLifetime: 0),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example', refreshTokenLifetime: 0),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example', idTokenLifetime: 0),
            static fn (): ProviderSettings => new ProviderSettings('https://id.example', scopes: ['email']),
        ] as $case) {
            try {
                $case();
                self::fail('Accepted.');
            } catch (InvalidValue $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }
    }

    public function testKeyRotation(): void
    {
        $keys = $this->keys();

        self::assertSame(['keys' => []], $keys->jwks());
        $first = $keys->active();
        self::assertSame(SigningAlgorithm::ES256, $first->algorithm);
        self::assertSame($first->kid, $keys->active()->kid);
        self::assertStringStartsWith('plain.', $this->keyStore->get($first->kid)?->privateMaterial() ?? '');
        self::assertSame([$first->publicJwk()], $keys->jwks()['keys']);

        $result = $keys->rotate();
        self::assertSame(0, $result['removed']);
        self::assertNull($result['activated']);
        self::assertIsString($result['created']);
        $next = $result['created'];
        self::assertCount(2, $keys->jwks()['keys']);
        self::assertSame($first->kid, $keys->active()->kid);
        self::assertNotNull($keys->publicKey($next));

        $this->services->clock->modify('+99 seconds');
        self::assertSame(['removed' => 0, 'activated' => null, 'created' => null], $keys->rotate());

        $this->services->clock->modify('+1 second');
        $result = $keys->rotate();
        self::assertSame($next, $result['activated']);
        self::assertIsString($result['created']);
        self::assertSame($next, $keys->active()->kid);
        self::assertCount(3, $keys->jwks()['keys']);
        self::assertNotNull($keys->publicKey($first->kid));

        $this->services->clock->modify('+200 seconds');
        self::assertNotNull($keys->publicKey($first->kid));
        $this->services->clock->modify('+1 second');
        self::assertNull($keys->publicKey($first->kid));
        self::assertCount(2, $keys->jwks()['keys']);
        self::assertTrue(array_is_list($keys->jwks()['keys']));
        $result = $keys->rotate(true);
        self::assertSame(1, $result['removed']);
        self::assertIsString($result['activated']);
        self::assertSame(IdentityEventType::SigningKeyRotated->value, array_last($this->services->audit->actions()));
    }

    public function testAFirstRotationActivatesAKeyAtOnce(): void
    {
        $keys = $this->keys();
        $result = $keys->rotate();

        self::assertSame($result['created'], $result['activated']);
        self::assertSame($result['activated'], $keys->active()->kid);

        $this->keyStore->reset();
        $first = $keys->rotate();
        $pending = $keys->rotate();
        self::assertNull($pending['activated']);
        $retired = $this->keyStore->get((string) $first['created']);
        $retired?->retire($this->services->clock->now());
        $this->keyStore->save(...array_filter([$retired]));
        $promoted = $keys->rotate();
        self::assertSame($pending['created'], $promoted['activated']);
        self::assertNotSame($promoted['activated'], $promoted['created']);
    }

    public function testClientRegistration(): void
    {
        $registry = $this->registry();
        [$client, $secret] = $registry->register('app', 'App', ['https://app.example/cb?x=1', 'https://APP.example:443/other/../cb'], ['https://app.example/out'], ['email', 'offline_access']);

        self::assertIsString($secret);
        self::assertSame(43, \strlen($secret));
        self::assertTrue($client->verifySecret($secret));
        self::assertSame(['https://app.example/cb?x=1', 'https://app.example/cb'], $client->redirectUris());
        self::assertSame(['openid', 'email', 'offline_access'], $client->scopes());
        self::assertEquals([$client], $registry->all());
        self::assertEquals($client, $registry->get('app'));
        self::assertSame([IdentityEventType::ClientRegistered->value, null, ['client' => 'app', 'public' => false, 'pkce' => true]], array_last($this->services->audit->records));

        [$public, $none] = $registry->register('spa', 'SPA', ['https://spa.example/cb'], public: true, firstParty: true);
        self::assertNull($none);
        self::assertTrue($public->isPublic());
        self::assertTrue($public->isFirstParty());
        self::assertTrue($public->requiresPkce());
        self::assertTrue($client->requiresPkce());

        [$optional] = $registry->register('server', 'Server', ['https://server.example/cb'], requirePkce: false);
        self::assertFalse($optional->requiresPkce());
        self::assertSame([IdentityEventType::ClientRegistered->value, null, ['client' => 'server', 'public' => false, 'pkce' => false]], array_last($this->services->audit->records));

        try {
            $registry->register('spa2', 'SPA', ['https://spa.example/cb'], public: true, requirePkce: false);
            self::fail('A public client goes without PKCE.');
        } catch (Refused $refused) {
            self::assertSame(['client.invalid', 'A public client proves itself by PKCE: it cannot go without.'], $refused->reasons);
        }

        $new = $registry->rotateSecret($client);
        $rotated = $registry->get('app');
        self::assertTrue($rotated?->verifySecret($new));
        self::assertFalse($rotated->verifySecret($secret));
        $registry->disable($client);
        self::assertTrue($registry->get('app')?->isDisabled());
        $registry->remove($public);
        self::assertNull($registry->get('spa'));

        [$local] = new ClientRegistry($this->clients, $this->services->clock, $this->settings, null, true)->register('dev', 'Dev', ['http://localhost:8000/cb', 'http://app.localhost/cb']);
        self::assertSame(['http://localhost:8000/cb', 'http://app.localhost/cb'], $local->redirectUris());
    }

    public function testClientRegistrationRefusals(): void
    {
        $registry = $this->registry();
        $registry->register('app', 'App', ['https://app.example/cb']);

        foreach ([
            ['app', ['https://app.example/cb'], [], 'client.taken'],
            ['new', ['https://app.example/cb'], ['admin'], 'client.scope_unknown'],
            ['new', ['http://app.example/cb'], [], 'client.redirect_invalid'],
            ['new', ['https://app.example/cb#frag'], [], 'client.redirect_invalid'],
            ['new', ['http://localhost/cb'], [], 'client.redirect_invalid'],
            ['new', ['not an address'], [], 'client.redirect_invalid'],
            ['new', [], [], 'client.invalid'],
            ['a', ['https://app.example/cb'], [], 'client.invalid'],
        ] as [$id, $uris, $scopes, $reason]) {
            try {
                $registry->register($id, 'Name', $uris, [], $scopes);
                self::fail($reason);
            } catch (Refused $refused) {
                self::assertSame($reason, $refused->reasons[0]);
            }
        }
    }

    public function testTheCodeFlowWithConsentAndRefresh(): void
    {
        $account = $this->account();
        [$client, $secret] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['email', 'profile', 'phone', 'offline_access']);
        $authorization = $this->authorization();
        $request = $authorization->parse($this->query(['scope' => 'openid email offline_access', 'state' => 'st', 'nonce' => 'no']));

        self::assertEquals($client, $authorization->client($request));
        self::assertTrue($authorization->needsConsent($account, $request));
        $authorization->grantConsent($account, $request);
        self::assertFalse($authorization->needsConsent($account, $request));
        self::assertTrue($authorization->needsConsent($account, $authorization->parse($this->query(['scope' => 'openid phone']))));
        self::assertTrue($authorization->needsConsent($account, $authorization->parse($this->query(['prompt' => 'consent']))));

        $authTime = $this->services->clock->now()->modify('-5 minutes');
        $redirect = $authorization->issueCode($request, $account, $authTime, ['pwd', 'otp'], 'session-id');
        self::assertMatchesRegularExpression('#^https://app\.example/cb\?code=[A-Za-z0-9_-]{43}&state=st&iss=https%3A%2F%2Fid\.example$#D', $redirect);
        $code = Query::get($redirect, 'code');

        $tokens = $this->tokens();
        self::assertEquals($client, $tokens->authenticateClient('app', $secret));
        $response = $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER);

        self::assertSame(['access_token', 'token_type', 'expires_in', 'id_token', 'scope', 'refresh_token'], array_keys($response));
        self::assertSame('Bearer', $response['token_type']);
        self::assertSame(600, $response['expires_in']);
        self::assertSame('openid email offline_access', $response['scope']);

        $keys = $this->keys();
        $idToken = Jws::decode((string) $response['id_token'], $keys->publicKey(...));
        self::assertSame(self::ISSUER, $idToken['iss']);
        self::assertSame($account->id(), $idToken['sub']);
        self::assertSame('app', $idToken['aud']);
        self::assertSame('no', $idToken['nonce']);
        self::assertSame(['pwd', 'otp'], $idToken['amr']);
        self::assertSame('session-id', $idToken['sid']);
        self::assertSame($authTime->getTimestamp(), $idToken['auth_time']);
        self::assertSame($this->services->clock->now()->getTimestamp() + 600, $idToken['exp']);
        self::assertSame(Base64Url::encode(substr(hash('sha256', (string) $response['access_token'], true), 0, 16)), $idToken['at_hash']);
        self::assertSame($idToken, $tokens->idTokenHint((string) $response['id_token']));
        self::assertNull($tokens->idTokenHint('nonsense'));

        $access = Jws::decode((string) $response['access_token'], $keys->publicKey(...));
        self::assertSame(['iss', 'sub', 'aud', 'client_id', 'scope', 'jti', 'iat', 'exp', 'auth_time'], array_keys($access));
        self::assertSame(self::ISSUER, $access['aud']);

        [$bearerAccount, $scopes] = $tokens->bearer((string) $response['access_token']);
        self::assertEquals($account, $bearerAccount);
        self::assertSame(['openid', 'email', 'offline_access'], $scopes);
        self::assertSame(['sub' => $account->id(), 'email' => 'ada@example.com', 'email_verified' => false], Claims::of($account, $scopes));

        $introspection = $tokens->introspect($client, (string) $response['access_token']);
        self::assertSame(['active' => true, 'scope' => 'openid email offline_access', 'client_id' => 'app', 'sub' => $account->id(), 'token_type' => 'Bearer', 'exp' => $access['exp'], 'iat' => $access['iat'], 'iss' => self::ISSUER], $introspection);
        self::assertSame('refresh_token', $tokens->introspect($client, (string) $response['refresh_token'])['token_type']);

        $this->services->clock->modify('+1 minute');
        $refreshed = $tokens->refresh($client, (string) $response['refresh_token'], 'openid');
        self::assertSame('openid offline_access', $refreshed['scope']);
        self::assertNotSame($response['refresh_token'], $refreshed['refresh_token']);
        self::assertArrayNotHasKey('nonce', Jws::decode((string) $refreshed['id_token'], $keys->publicKey(...)));

        $this->assertOAuthError('invalid_scope', static fn (): array => $tokens->refresh($client, (string) $refreshed['refresh_token'], 'openid phone'));
        $again = $tokens->refresh($client, (string) $refreshed['refresh_token'], null);
        self::assertSame('openid offline_access', $again['scope']);

        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $response['refresh_token'], null));
        self::assertContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $again['refresh_token'], null));
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $again['access_token']));
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $again['access_token']));
    }

    public function testACodeIsUsedOnceByItsClientWithItsVerifier(): void
    {
        $account = $this->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        [$other] = $this->registry()->register('other', 'Other', ['https://app.example/cb']);
        $authorization = $this->authorization();
        $tokens = $this->tokens();
        $issue = (fn (): string => Query::get($authorization->issueCode($authorization->parse($this->query(['scope' => 'openid offline_access'])), $account, $this->services->clock->now(), [], null), 'code'));

        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, 'unknown', 'https://app.example/cb', self::VERIFIER));
        $code = $issue();
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($other, $code, 'https://app.example/cb', self::VERIFIER));
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/other', self::VERIFIER));
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER));

        $code = $issue();
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', str_repeat('a', 43)));

        $code = $issue();
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', null));

        $code = $issue();
        $this->services->clock->modify('+60 seconds');
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER));

        $code = $issue();
        $response = $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER);
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER));
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $response['access_token']));
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $response['refresh_token'], null));

        $code = $issue();
        $account->disable($this->services->clock->now());
        $this->services->accounts->save($account);
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER));
    }

    public function testACodeIssuedWithoutAChallengeTakesNoVerifier(): void
    {
        $account = $this->account();
        [$client] = $this->registry()->register('server', 'Server', ['https://app.example/cb'], requirePkce: false);
        $authorization = $this->authorization();
        $tokens = $this->tokens();
        $issue = (fn (): string => Query::get($authorization->issueCode($authorization->parse(['response_type' => 'code', 'client_id' => 'server', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'nonce' => 'n']), $account, $this->services->clock->now(), [], null), 'code'));

        $code = $issue();
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER));

        $response = $tokens->exchangeCode($client, $issue(), 'https://app.example/cb', null);
        self::assertSame('n', Jws::decode((string) $response['id_token'], $this->keys()->publicKey(...))['nonce']);
    }

    public function testClientAuthentication(): void
    {
        [$client, $secret] = $this->registry()->register('app', 'App', ['https://app.example/cb']);
        $this->registry()->register('spa', 'SPA', ['https://app.example/cb'], public: true);
        $tokens = $this->tokens();

        self::assertSame('spa', $tokens->authenticateClient('spa', null)->id());
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('spa', 'secret'));
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('app', null));
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('app', 'wrong'));
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('nobody', 'x'));
        $this->registry()->disable($client);
        $this->assertOAuthError('invalid_client', static fn (): Client => $tokens->authenticateClient('app', $secret));
    }

    public function testRevocationAndIntrospection(): void
    {
        $account = $this->account();
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        [$other] = $this->registry()->register('other', 'Other', ['https://app.example/cb']);
        $response = $this->flow($account, 'openid offline_access');
        $tokens = $this->tokens();

        $tokens->revoke($other, (string) $response['access_token']);
        $tokens->revoke($client, 'unknown');
        $tokens->revoke($client, 'a.b.c');
        self::assertSame(['active' => false], $tokens->introspect($other, (string) $response['access_token']));
        self::assertSame(['active' => false], $tokens->introspect($client, 'a.b.c'));
        self::assertTrue($tokens->introspect($client, (string) $response['access_token'])['active']);

        $tokens->revoke($client, (string) $response['access_token']);
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $response['access_token']));
        self::assertTrue($tokens->introspect($client, (string) $response['refresh_token'])['active']);
        self::assertSame([IdentityEventType::TokenRevoked->value, $account->id(), ['client' => 'app', 'kind' => 'access']], array_last($this->services->audit->records));

        $second = $this->flow($account, 'openid offline_access');
        $tokens->revoke($client, (string) $second['refresh_token']);
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $second['access_token']));
        self::assertNotContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());

        $third = $this->flow($account, 'openid');
        self::assertArrayNotHasKey('refresh_token', $third);
        $this->services->accounts->remove($account);
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $third['access_token']));
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $third['access_token']));
    }

    public function testBearerRefusals(): void
    {
        $account = $this->account();
        $this->registry()->register('app', 'App', ['https://app.example/cb']);
        $response = $this->flow($account, 'openid');
        $tokens = $this->tokens();

        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer('nonsense'));
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $response['id_token']));
        $other = new Tokens($this->clients, $this->grants, $this->services->accounts, $this->keys(), new ProviderSettings('https://other.example', SigningAlgorithm::ES256), $this->services->clock, $this->services->journal);
        $this->assertOAuthError('invalid_token', static fn (): array => $other->bearer((string) $response['access_token']));
        self::assertNull($other->idTokenHint((string) $response['id_token']));

        $account->disable($this->services->clock->now());
        $this->services->accounts->save($account);
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $response['access_token']));
    }

    public function testUserinfoRefusesTheTokensOfADisabledClient(): void
    {
        $account = $this->account();
        $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['email']);
        $response = $this->flow($account, 'openid email');
        $tokens = $this->tokens();
        self::assertSame($account->id(), $tokens->bearer((string) $response['access_token'])[0]->id());

        $client = $this->registry()->get('app');
        self::assertNotNull($client);
        $this->registry()->disable($client);

        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $response['access_token']));

        $this->clients->remove($client);
        $this->assertOAuthError('invalid_token', static fn (): array => $tokens->bearer((string) $response['access_token']));
    }

    public function testConsentWithdrawalRevokesTheClientsTokens(): void
    {
        $account = $this->account();
        $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $authorization = $this->authorization();
        $authorization->grantConsent($account, $authorization->parse($this->query()));

        $response = $this->flow($account, 'openid offline_access');

        self::assertCount(1, $authorization->consentsOf($account));
        $authorization->withdrawConsent($account, 'app');
        self::assertSame([], $authorization->consentsOf($account));
        $this->assertOAuthError('invalid_token', fn (): array => $this->tokens()->bearer((string) $response['access_token']));
        $authorization->withdrawConsent($account, 'app');
        self::assertSame(IdentityEventType::ConsentWithdrawn->value, array_last($this->services->audit->actions()));
    }

    public function testAFirstPartyClientNeedsNoConsentAndErrorsGoBack(): void
    {
        $account = $this->account();
        $this->registry()->register('app', 'App', ['https://app.example/cb'], firstParty: true);
        $authorization = $this->authorization();
        $request = $authorization->parse($this->query(['state' => 's']));

        self::assertFalse($authorization->needsConsent($account, $request));
        self::assertSame('https://app.example/cb?error=login_required&state=s&iss=https%3A%2F%2Fid.example', $authorization->errorRedirect($request, 'login_required'));
        self::assertSame('https://app.example/cb?error=consent_required&iss=https%3A%2F%2Fid.example', $authorization->errorRedirect($authorization->parse($this->query()), 'consent_required'));

        $this->expectException(AuthorizationError::class);
        $this->clients->reset();
        $authorization->client($request);
    }

    public function testClaimsByScope(): void
    {
        $account = $this->account();
        self::assertSame(['sub' => $account->id()], Claims::of($account, ['openid', 'phone']));
        $account->rename(null);
        self::assertSame(['sub' => $account->id()], Claims::of($account, ['openid', 'profile']));
        $account->rename('Ada');
        $account->verifyPhone('+15145550123', $this->services->clock->now());
        $account->verifyEmail($this->services->clock->now());

        $this->services->accounts->save($account);
        self::assertSame(['sub' => $account->id(), 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada', 'phone_number' => '+15145550123', 'phone_number_verified' => true], Claims::of($account, ['openid', 'email', 'profile', 'phone']));
    }

    /**
     * @return array<string, int|string>
     */
    private function flow(Account $account, string $scope): array
    {
        $authorization = $this->authorization();
        $code = Query::get($authorization->issueCode($authorization->parse($this->query(['scope' => $scope])), $account, $this->services->clock->now(), [], 'sid'), 'code');
        $client = $this->clients->get('app');
        self::assertNotNull($client);

        return $this->tokens()->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER);
    }

    private function account(): Account
    {
        return $this->services->account();
    }

    private function keys(): SigningKeys
    {
        return new SigningKeys($this->keyStore, new PlainSecretCipher(), $this->settings, $this->services->clock, new LockFactory(new InMemoryStore()), $this->services->journal);
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal);
    }

    private function authorization(): Authorization
    {
        return new Authorization($this->clients, $this->grants, $this->settings, $this->services->clock, $this->services->journal);
    }

    private function tokens(): Tokens
    {
        return new Tokens($this->clients, $this->grants, $this->services->accounts, $this->keys(), $this->settings, $this->services->clock, $this->services->journal);
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, string>
     */
    private function query(array $query = []): array
    {
        return $query + ['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256'];
    }

    private function assertOAuthError(string $error, callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted, expected '.$error);
        } catch (OAuthError $oAuthError) {
            self::assertSame($error, $oAuthError->error);
            self::assertSame($error, $oAuthError->toArray()['error']);
        }
    }
}
