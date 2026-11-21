<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Oidc\ClientAssertions;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\AuthorizeController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\ClientCredentials;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\EndSessionController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\MetadataController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\OAuthResponses;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\TokenController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\UserinfoController;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The OpenID Connect provider seen by a client: discovery, keys, the authorization code flow with PKCE and consent,
 * the token endpoint, userinfo, introspection, revocation and RP-initiated logout.
 */
#[CoversClass(MetadataController::class)]
#[CoversClass(AuthorizeController::class)]
#[CoversClass(TokenController::class)]
#[CoversClass(UserinfoController::class)]
#[CoversClass(EndSessionController::class)]
#[CoversClass(ClientCredentials::class)]
#[CoversClass(OAuthResponses::class)]
final class OidcFlowTest extends FunctionalTestCase
{
    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    public function testDiscoveryAndKeys(): void
    {
        $this->client->request('GET', '/.well-known/openid-configuration');
        self::assertResponseIsSuccessful();
        $metadata = $this->json();
        self::assertSame('http://localhost', $metadata['issuer']);
        self::assertSame('http://localhost/oidc/authorize', $metadata['authorization_endpoint']);
        self::assertSame('http://localhost/oidc/token', $metadata['token_endpoint']);
        self::assertSame('http://localhost/oidc/jwks', $metadata['jwks_uri']);
        self::assertSame(['code'], $metadata['response_types_supported']);
        self::assertSame(['S256'], $metadata['code_challenge_methods_supported']);
        self::assertSame(['none', 'login', 'consent'], $metadata['prompt_values_supported'], 'The provider shows no account chooser: select_account is not offered.');
        self::assertTrue($metadata['authorization_response_iss_parameter_supported']);
        self::assertStringContainsString('max-age=300', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('*', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));

        $this->client->request('GET', '/oidc/jwks');
        self::assertResponseIsSuccessful();
        $keys = $this->json()['keys'] ?? null;
        self::assertIsArray($keys);
        self::assertSame([], $keys);
    }

    public function testTheAuthorizationCodeFlow(): void
    {
        $account = $this->account();
        [, $secret] = $this->service(ClientRegistry::class)->register('app', 'The App', ['https://app.example/cb'], ['https://app.example/bye'], ['email', 'offline_access']);
        self::assertIsString($secret);
        $authorize = '/oidc/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid email offline_access', 'state' => 'xyz', 'nonce' => 'n-1', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']);

        $this->client->request('GET', $authorize.'&prompt=none');
        self::assertResponseRedirects('https://app.example/cb?error=login_required&state=xyz&iss=http%3A%2F%2Flocalhost');

        $this->client->request('GET', $authorize);
        self::assertResponseRedirects('http://localhost/login');
        $this->signIn();
        self::assertStringStartsWith('http://localhost/oidc/authorize?', $this->location());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'The App asks for access');
        self::assertSelectorTextContains('main', 'See your email address');

        $this->client->request('GET', $authorize.'&prompt=none');
        self::assertResponseRedirects('https://app.example/cb?error=consent_required&state=xyz&iss=http%3A%2F%2Flocalhost');

        $crawler = $this->client->request('GET', $authorize);
        $this->client->submit($crawler->selectButton('Allow')->form());
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $query = Query::of($this->location());
        self::assertStringStartsWith('https://app.example/cb?code=', $this->location());
        self::assertSame(['xyz', 'http://localhost'], [$query['state'] ?? null, $query['iss'] ?? null]);

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'authorization_code', 'code' => $query['code'] ?? '', 'redirect_uri' => 'https://app.example/cb', 'code_verifier' => self::VERIFIER], server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('app:'.$secret)]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $tokens = $this->json();
        self::assertSame('Bearer', $tokens['token_type']);
        self::assertIsString($tokens['access_token']);
        self::assertIsString($tokens['id_token']);
        self::assertIsString($tokens['refresh_token']);

        $this->client->request('GET', '/oidc/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid email', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']));
        self::assertStringStartsWith('https://app.example/cb?code=', $this->location());

        $this->client->request('GET', '/oidc/userinfo', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$tokens['access_token']]);
        self::assertResponseIsSuccessful();
        self::assertSame(['sub' => $account->id(), 'email' => 'ada@example.com', 'email_verified' => true], $this->json());

        $this->client->request('POST', '/oidc/userinfo', ['access_token' => $tokens['access_token']]);
        self::assertResponseIsSuccessful();
        self::assertSame(['sub' => $account->id(), 'email' => 'ada@example.com', 'email_verified' => true], $this->json());

        $this->client->request('POST', '/oidc/userinfo', ['access_token' => $tokens['access_token']], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$tokens['access_token']]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_request', $this->json()['error'] ?? null);

        $this->client->request('POST', '/oidc/userinfo', ['access_token' => '']);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/oidc/userinfo?access_token='.$tokens['access_token']);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/oidc/userinfo', server: ['HTTP_AUTHORIZATION' => 'Basic '.$tokens['access_token']]);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/oidc/userinfo');
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Bearer realm="userinfo", error="invalid_token"', $this->client->getResponse()->headers->get('WWW-Authenticate'));

        $this->client->request('GET', '/oidc/jwks');
        self::assertCount(1, (array) ($this->json()['keys'] ?? []));

        $this->client->request('POST', '/oidc/introspect', ['token' => $tokens['access_token'], 'client_id' => 'app', 'client_secret' => $secret]);
        self::assertTrue($this->json()['active'] ?? null);

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => 'app', 'client_secret' => $secret]);
        self::assertResponseIsSuccessful();
        $refreshed = $this->json();
        self::assertNotSame($tokens['refresh_token'], $refreshed['refresh_token'] ?? null);

        $this->client->request('POST', '/oidc/revoke', ['token' => $refreshed['refresh_token'] ?? '', 'client_id' => 'app', 'client_secret' => $secret]);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/oidc/introspect', ['token' => $refreshed['access_token'] ?? '', 'client_id' => 'app', 'client_secret' => $secret]);
        self::assertSame(['active' => false], $this->json());

        $this->client->request('GET', '/oidc/logout?'.http_build_query(['id_token_hint' => $tokens['id_token'], 'post_logout_redirect_uri' => 'https://app.example/bye', 'state' => 's']));
        self::assertResponseRedirects('https://app.example/bye?state=s');
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testASessionWithoutProofSignsInAgainForMaxAge(): void
    {
        $account = $this->account();
        $this->service(ClientRegistry::class)->register('app', 'The App', ['https://app.example/cb'], firstParty: true);
        $authorize = '/oidc/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'state' => 'xyz', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']);
        // A token without proofs, as a session restored by remember_me holds.
        $this->client->loginUser(new AccountUser($account));

        $this->client->request('GET', $authorize.'&max_age=3600');
        self::assertResponseRedirects();
        self::assertStringStartsNotWith('https://app.example/cb', $this->location());

        $this->client->request('GET', $authorize.'&max_age=3600&prompt=none');
        self::assertResponseRedirects('https://app.example/cb?error=login_required&state=xyz&iss=http%3A%2F%2Flocalhost');

        $this->client->request('GET', $authorize);
        self::assertStringStartsWith('https://app.example/cb?code=', $this->location());
    }

    public function testTheTokenEndpointRefusals(): void
    {
        [, $secret] = $this->service(ClientRegistry::class)->register('app', 'The App', ['https://app.example/cb']);
        self::assertIsString($secret);

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'authorization_code', 'code' => 'x', 'redirect_uri' => 'https://app.example/cb', 'code_verifier' => self::VERIFIER], server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('app:wrong')]);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Basic realm="token"', $this->client->getResponse()->headers->get('WWW-Authenticate'));
        self::assertSame('invalid_client', $this->error());

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'authorization_code', 'code' => 'x', 'redirect_uri' => 'https://app.example/cb', 'code_verifier' => str_repeat('a', 4097), 'client_id' => 'app', 'client_secret' => $secret]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_request', $this->error());

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'password', 'client_id' => 'app', 'client_secret' => $secret]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('unsupported_grant_type', $this->error());

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'authorization_code', 'client_id' => 'app', 'client_secret' => $secret]);
        self::assertSame('invalid_request', $this->error());

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'authorization_code', 'code' => 'x', 'client_secret' => $secret]);
        self::assertSame('invalid_request', $this->error());

        $this->client->request('POST', '/oidc/token', ['client_secret' => 'x'], server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('app:'.$secret)]);
        self::assertSame('invalid_request', $this->error());

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'authorization_code'], server: ['HTTP_AUTHORIZATION' => 'Basic !!!']);
        self::assertSame('invalid_client', $this->error());

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'authorization_code', 'code' => 'unknown', 'redirect_uri' => 'https://app.example/cb', 'code_verifier' => self::VERIFIER], server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode(urlencode('app').':'.urlencode($secret))]);
        self::assertSame('invalid_grant', $this->error());

        $this->service(ClientRegistry::class)->register('spa', 'SPA', ['https://app.example/cb'], public: true);
        $this->client->request('POST', '/oidc/introspect', ['token' => 'x', 'client_id' => 'spa']);
        self::assertResponseStatusCodeSame(401);
        $this->client->request('POST', '/oidc/revoke', ['client_id' => 'spa']);
        self::assertSame('invalid_request', $this->error());
        $this->client->request('POST', '/oidc/revoke', ['token' => 'x', 'client_id' => 'spa']);
        self::assertResponseIsSuccessful();
    }

    public function testAuthorizationErrors(): void
    {
        $this->service(ClientRegistry::class)->register('app', 'The App', ['https://app.example/cb']);

        $this->client->request('GET', '/oidc/authorize?client_id=unknown&redirect_uri=https://evil.example');
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorTextContains('code', 'invalid_client');

        $this->client->request('GET', '/oidc/authorize?client_id=app&redirect_uri=https://app.example/cb&response_type=token&state=s');
        self::assertResponseRedirects('https://app.example/cb?error=unsupported_response_type&error_description=Only%20the%20authorization%20code%20flow%20is%20supported.&state=s&iss=http%3A%2F%2Flocalhost');

        $this->account();
        $this->signIn();
        $crawler = $this->client->request('GET', '/oidc/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']));
        $this->client->submit($crawler->selectButton('Deny')->form());
        self::assertResponseRedirects('https://app.example/cb?error=access_denied&iss=http%3A%2F%2Flocalhost');

        $this->client->request('GET', '/oidc/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'prompt' => 'login', 'state' => 'again', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']));
        self::assertResponseRedirects('/reauthenticate');

        // The password proves it again through the application's form, which comes back to the request without `login`.
        $this->signIn();
        self::assertResponseRedirects('/oidc/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'state' => 'again', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256'], encoding_type: \PHP_QUERY_RFC3986));
    }

    public function testAPostedRequestComesBackAfterTheSignIn(): void
    {
        $this->account();
        $this->service(ClientRegistry::class)->register('app', 'The App', ['https://app.example/cb'], firstParty: true);
        $parameters = ['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'state' => 'posted', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256'];

        $this->client->request('POST', '/oidc/authorize', $parameters);
        self::assertResponseRedirects('http://localhost/login');

        $this->signIn();
        self::assertResponseRedirects('/oidc/authorize?'.http_build_query($parameters, encoding_type: \PHP_QUERY_RFC3986));
        $this->client->followRedirect();
        self::assertStringStartsWith('https://app.example/cb?code=', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertStringContainsString('&state=posted&iss=', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testLogoutWithoutAHintAsksFirst(): void
    {
        $this->account();
        $this->signIn();
        $this->service(ClientRegistry::class)->register('app', 'The App', ['https://app.example/cb'], ['https://app.example/bye']);

        $crawler = $this->client->request('GET', '/oidc/logout?client_id=app&post_logout_redirect_uri=https://app.example/bye');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sign out');
        $this->client->submit($crawler->selectButton('Sign out')->form());
        self::assertResponseRedirects('https://app.example/bye');

        $this->client->request('GET', '/oidc/logout?client_id=app&post_logout_redirect_uri=https://evil.example');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'You are signed out');
    }

    public function testAClientAssertionAndTheClientCredentialsGrant(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::ES256, 'k1');
        $this->service(ClientRegistry::class)->register('machine', 'Machine', [], jwks: [$key->publicJwk()], resources: ['https://api.example/', 'https://other.example/']);
        $now = time();
        $assertion = static fn (array $claims = []): string => Jws::encode($claims + ['iss' => 'machine', 'sub' => 'machine', 'aud' => 'http://localhost', 'jti' => bin2hex(random_bytes(8)), 'exp' => $now + 60], $key);
        $form = static fn (string $assertion, array $more = []): array => $more + ['grant_type' => 'client_credentials', 'resource' => 'https://api.example', 'client_assertion_type' => ClientAssertions::TYPE, 'client_assertion' => $assertion];

        // The audience is the issuer alone, as a string (draft-ietf-oauth-rfc7523bis).
        $this->client->request('POST', '/oidc/token', $form($assertion(['aud' => 'http://localhost/oidc/token'])));
        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_client', $this->json()['error'] ?? null);
        $this->client->request('POST', '/oidc/token', $form($assertion(['aud' => ['http://localhost']])));
        self::assertResponseStatusCodeSame(401);

        $this->client->request('POST', '/oidc/token', $form($assertion()));
        self::assertResponseIsSuccessful();
        $answer = $this->json();
        self::assertSame(['access_token', 'token_type', 'expires_in'], array_keys($answer));
        self::assertIsString($answer['access_token']);

        $this->client->request('POST', '/oidc/introspect', ['token' => $answer['access_token'], 'client_assertion_type' => ClientAssertions::TYPE, 'client_assertion' => $assertion(['aud' => 'http://localhost'])]);
        self::assertSame(['active' => true, 'client_id' => 'machine', 'sub' => 'machine', 'aud' => 'https://api.example/'], array_intersect_key($this->json(), ['active' => 1, 'client_id' => 1, 'sub' => 1, 'aud' => 1]));

        $this->client->request('GET', '/oidc/userinfo', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$answer['access_token']]);
        self::assertResponseStatusCodeSame(401);

        $used = $assertion();
        $this->client->request('POST', '/oidc/token', $form($used));
        $this->client->request('POST', '/oidc/token', $form($used));
        self::assertSame('invalid_client', $this->error(), 'An assertion is used once.');

        foreach ([
            $form($assertion(), ['client_id' => 'other']),
            $form($assertion(), ['client_assertion_type' => 'urn:other']),
            $form($assertion(['aud' => 'http://evil.example/oidc/token'])),
            $form('not-a-token'),
            $form($assertion(['sub' => null])),
            $form($assertion(), ['client_assertion' => ['x']]),
            $form($assertion(), ['client_id' => ['machine']]),
        ] as $refused) {
            $this->client->request('POST', '/oidc/token', $refused);
            self::assertSame('invalid_client', $this->error(), (string) json_encode($refused));
        }

        $this->client->request('POST', '/oidc/token', $form($assertion(), ['client_id' => 'machine', 'resource' => 'https://other.example']));
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/oidc/token', $form($assertion(), ['client_secret' => 'x']));
        self::assertSame('invalid_request', $this->error());
        $this->client->request('POST', '/oidc/token', $form($assertion()), server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('machine:x')]);
        self::assertSame('invalid_request', $this->error());

        foreach ([['resource' => 'https://unknown.example'], ['resource' => ['https://api.example', 'https://other.example']], ['resource' => ''], ['resource' => 'https://api.example/'.str_repeat('a', 2048)]] as $target) {
            $this->client->request('POST', '/oidc/token', $form($assertion(), $target));
            self::assertSame('invalid_target', $this->error(), (string) json_encode($target));
        }

        $this->client->request('POST', '/oidc/token', ['grant_type' => 'client_credentials', 'resource' => 'https://api.example/'.str_repeat('a', 2030), 'client_assertion_type' => ClientAssertions::TYPE, 'client_assertion' => $assertion()]);
        self::assertSame('invalid_target', $this->error(), 'A resource of 2048 bytes at most is read, then refused as unknown.');

        $this->client->request('GET', '/.well-known/openid-configuration');
        $metadata = $this->json();
        self::assertSame(['client_secret_basic', 'client_secret_post', 'private_key_jwt', 'none'], $metadata['token_endpoint_auth_methods_supported']);
        self::assertSame(['client_secret_basic', 'client_secret_post', 'private_key_jwt'], $metadata['introspection_endpoint_auth_methods_supported']);
        self::assertSame(['RS256', 'ES256', 'EdDSA'], $metadata['token_endpoint_auth_signing_alg_values_supported']);
        self::assertSame(['authorization_code', 'refresh_token', 'client_credentials'], $metadata['grant_types_supported']);
        self::assertArrayNotHasKey('acr_values_supported', $metadata);
    }

    /**
     * The error code of the answer.
     *
     * @phpstan-impure
     */
    private function error(): mixed
    {
        return $this->json()['error'] ?? null;
    }

    /**
     * @return array<string, mixed>
     *
     * @phpstan-impure
     */
    private function json(): array
    {
        $json = json_decode($this->content(), true);
        self::assertIsArray($json);

        /** @var array<string, mixed> $json */
        return $json;
    }
}
