<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Phil\IdentityClientBundle\Application\Event\ReturnToDiscarded;
use Phil\IdentityClientBundle\Application\Event\UserSignedIn;
use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientConfiguration;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientServices;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\FirewallComposer;
use Phil\IdentityClientBundle\Infrastructure\Security\AuthorizationRequestListener;
use Phil\IdentityClientBundle\Infrastructure\Security\IdentityUserProvider;
use Phil\IdentityClientBundle\Infrastructure\Security\SignInListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use Phil\IdentityClientBundle\PhilIdentityClientBundle;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PhilIdentityClientBundle::class)]
#[CoversClass(ClientConfiguration::class)]
#[CoversClass(ClientServices::class)]
#[CoversClass(FirewallComposer::class)]
#[CoversClass(SignInListener::class)]
#[CoversClass(AuthorizationRequestListener::class)]
#[CoversClass(IdentityUserProvider::class)]
#[CoversClass(VerifiedIdentity::class)]
final class SignInTest extends FunctionalTestCase
{
    public function testASignInLandsOnTheRootAndKeepsNoTokenItHasNoUseFor(): void
    {
        $this->boot();

        $response = $this->signIn();

        self::assertTrue($response->isRedirect('/') || $response->isRedirect('http://localhost/'), (string) $response->headers->get('Location'));
        $token = $this->securityToken();
        self::assertNotNull($token);
        self::assertSame('subject-1', $token->getUserIdentifier());
        self::assertFalse($token->hasAttribute(TokenAttribute::ACCESS_TOKEN));
        self::assertFalse($token->hasAttribute(TokenAttribute::REFRESH_TOKEN));
        self::assertFalse($token->hasAttribute(TokenAttribute::ACCESS_TOKEN_EXPIRES_AT));
        self::assertFalse($token->hasAttribute(TokenAttribute::ID_TOKEN));
        self::assertSame(FakeOidcProvider::SESSION_ID, $token->getAttribute(TokenAttribute::PROVIDER_SESSION));

        $signedIn = $this->events()->of(UserSignedIn::class);
        self::assertCount(1, $signedIn);
        self::assertSame('subject-1', $signedIn[0]->subject);
        self::assertSame(FakeOidcProvider::SESSION_ID, $signedIn[0]->providerSessionId);
    }

    public function testTheTokensAskedForAreKept(): void
    {
        $this->boot(['tokens' => ['keep_access_token' => true, 'keep_id_token' => true]]);

        $this->signIn();

        $token = $this->securityToken();
        self::assertNotNull($token);
        self::assertIsString($token->getAttribute(TokenAttribute::ACCESS_TOKEN));
        self::assertIsString($token->getAttribute(TokenAttribute::ID_TOKEN));
        self::assertIsInt($token->getAttribute(TokenAttribute::ACCESS_TOKEN_EXPIRES_AT));
        self::assertFalse($token->hasAttribute(TokenAttribute::REFRESH_TOKEN));
    }

    public function testTheEndSessionFeatureKeepsTheIdToken(): void
    {
        $this->boot(['end_session' => ['enabled' => true]]);

        $this->signIn();

        $token = $this->securityToken();
        self::assertNotNull($token);
        self::assertIsString($token->getAttribute(TokenAttribute::ID_TOKEN));
        self::assertFalse($token->hasAttribute(TokenAttribute::ACCESS_TOKEN));
    }

    public function testTheProvisionerReceivesOnlyTheKeptClaims(): void
    {
        $this->boot();

        $this->signIn(['email' => 'a@example.test', 'email_verified' => true, 'name' => 'Ada', 'groups' => ['admin']]);

        $identity = $this->provisioner()->last;
        self::assertNotNull($identity);
        self::assertSame(FakeOidcProvider::ISSUER, $identity->issuer);
        self::assertSame('subject-1', $identity->subject);
        self::assertSame(['name' => 'Ada', 'email' => 'a@example.test'], $identity->claims);
        self::assertSame('a@example.test', $identity->verifiedEmail());
    }

    public function testAnUnverifiedEmailNeverReachesTheProvisioner(): void
    {
        $this->boot();

        $this->signIn(['email' => 'victim@example.test', 'email_verified' => false]);

        $identity = $this->provisioner()->last;
        self::assertNotNull($identity);
        self::assertNull($identity->verifiedEmail());
        self::assertArrayNotHasKey('email', $identity->claims);
    }

    public function testADeletedAccountEndsTheSession(): void
    {
        $this->boot();
        $this->signIn();
        $this->provisioner()->exists = false;

        self::assertTrue($this->get('/private')->isRedirection());
        self::assertNull($this->securityToken());
    }

    public function testTheClaimsCanComeFromTheUserinfoEndpoint(): void
    {
        $this->boot(['claims_source' => 'userinfo']);

        $this->signIn();

        self::assertSame(['name' => 'From Userinfo'], $this->provisioner()->last?->claims);
    }

    public function testAForeignStateIsRefused(): void
    {
        $this->boot();
        $authorization = $this->startSignIn();

        $callback = $this->provider()->authorize($authorization);
        $response = $this->get(preg_replace('/state=[^&]+/', 'state=forged', $callback) ?? '');

        self::assertSame('/', parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH));
        self::assertNull($this->securityToken());
        self::assertSame([], $this->provider()->tokenRequests);
    }

    public function testAMismatchedNonceIsRefused(): void
    {
        $this->assertRefused(['nonce' => 'from-another-flow']);
    }

    public function testAWrongAudienceIsRefused(): void
    {
        $this->assertRefused(['aud' => 'another-client']);
    }

    public function testAWrongIssuerIsRefused(): void
    {
        $this->assertRefused(['iss' => 'https://evil.example.test']);
    }

    public function testAnExpiredIdTokenIsRefused(): void
    {
        $this->assertRefused(['exp' => 1]);
    }

    public function testAnUnsignedIdTokenIsRefused(): void
    {
        $this->assertForgeryRefused(static fn (string $claims): string => FakeOidcProvider::b64((string) json_encode(['alg' => 'none'])).'.'.FakeOidcProvider::b64($claims).'.');
    }

    public function testAnIdTokenSignedWithThePublicKeyAsASecretIsRefused(): void
    {
        $this->assertForgeryRefused(static function (string $claims): string {
            $input = FakeOidcProvider::b64((string) json_encode(['alg' => 'HS256', 'kid' => FakeOidcProvider::key()->get('kid')])).'.'.FakeOidcProvider::b64($claims);

            return $input.'.'.FakeOidcProvider::b64(hash_hmac('sha256', $input, (string) json_encode(FakeOidcProvider::key()->toPublic()), true));
        });
    }

    public function testAnIdTokenSignedWithAnotherThanThePinnedAlgorithmIsRefused(): void
    {
        $this->assertForgeryRefused(static function (string $claims): string {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($claims, true, 8, \JSON_THROW_ON_ERROR);

            return FakeOidcProvider::sign($decoded, 'RS512');
        });
    }

    public function testAKeyEmbeddedInTheHeaderIsNeverFollowed(): void
    {
        $this->assertForgeryRefused(static function (string $claims): string {
            $attacker = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig']);
            $jws = new JWSBuilder(new AlgorithmManager([new RS256()]))->create()->withPayload($claims)
                ->addSignature($attacker, ['alg' => 'RS256', 'jwk' => $attacker->toPublic()->jsonSerialize(), 'jku' => 'https://evil.example.test/keys'])
                ->build();

            return new CompactSerializer()->serialize($jws, 0);
        });
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedReturnTo(): iterable
    {
        yield 'a path of the application' => ['/private?tab=a', 'http://localhost/private?tab=a'];
        yield 'a declared origin' => ['http://localhost/cart?a=1', 'http://localhost/cart?a=1'];
    }

    #[DataProvider('acceptedReturnTo')]
    public function testAValidReturnToIsWhereTheSignInLands(string $returnTo, string $expected): void
    {
        $this->boot();

        $response = $this->signIn([], '/oidc/start?return_to='.urlencode($returnTo));

        self::assertSame($expected, $response->headers->get('Location'));
        self::assertSame([], $this->events()->of(ReturnToDiscarded::class));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedReturnTo(): iterable
    {
        yield 'another origin' => ['https://evil.example.test/'];
        yield 'a declared origin outside the session cookie domain' => ['https://shop.example.test/cart'];
        yield 'a scheme-relative address' => ['//evil.example.test/'];
        yield 'a backslash' => ['/\evil.example.test/'];
        yield 'a normalized double slash' => ['/.//evil.example.test/'];
    }

    #[DataProvider('refusedReturnTo')]
    public function testARefusedReturnToLandsOnTheRootWithoutAnError(string $returnTo): void
    {
        $this->boot();

        $response = $this->signIn([], '/oidc/start?return_to='.urlencode($returnTo));

        self::assertSame('http://localhost/', $response->headers->get('Location'));
    }

    public function testAnArrayReturnToIsDiscarded(): void
    {
        $this->boot();

        $response = $this->signIn([], '/oidc/start?return_to[]=/private');

        self::assertSame('http://localhost/', $response->headers->get('Location'));
        self::assertCount(1, $this->events()->of(ReturnToDiscarded::class));
    }

    public function testARefusedReturnToForgetsAnEarlierTarget(): void
    {
        $this->boot();

        self::assertTrue($this->get('/private')->isRedirection());
        $response = $this->signIn([], '/oidc/start?return_to='.urlencode('https://evil.example.test/'));

        self::assertSame('http://localhost/', $response->headers->get('Location'));
    }

    public function testAProtectedPageIsWhereTheSignInReturnsWithoutAReturnTo(): void
    {
        $this->boot();

        $authorization = (string) $this->get('/private')->headers->get('Location');
        $response = $this->finishSignIn($authorization);

        self::assertSame('http://localhost/private', $response->headers->get('Location'));
    }

    public function testTheReturnToIsNeverSentToTheProvider(): void
    {
        $this->boot();

        $authorization = $this->startSignIn('/oidc/start?return_to=/private');
        self::assertSame([], $this->events()->of(ReturnToDiscarded::class));

        self::assertStringNotContainsString('return_to', $authorization);
        self::assertStringNotContainsString('private', $authorization);
    }

    public function testTheParameterNameIsConfigurable(): void
    {
        $this->boot(['return_to' => ['parameter' => 'next']]);

        $response = $this->signIn([], '/oidc/start?next=/private');

        self::assertSame('http://localhost/private', $response->headers->get('Location'));
    }

    public function testTheAuthorizationRequestUsesThePinnedOptions(): void
    {
        $this->boot(['scopes' => ['openid', 'profile'], 'authorization_params' => ['ui_locales' => 'fr-CA'], 'elevation' => ['max_age' => 3600]]);

        $query = FakeOidcProvider::query($this->startSignIn());

        self::assertSame('code', $query['response_type']);
        self::assertSame(FakeOidcProvider::CLIENT_ID, $query['client_id']);
        self::assertSame('openid profile', $query['scope']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame('fr-CA', $query['ui_locales']);
        self::assertSame('3600', $query['max_age']);
        self::assertArrayNotHasKey('acr_values', $query);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertRefused(array $claims): void
    {
        $this->boot();

        $this->assertNotSignedIn($this->signIn($claims));
    }

    /**
     * @param callable(string): string $forger
     */
    private function assertForgeryRefused(callable $forger): void
    {
        $this->boot();
        $this->provider()->idTokenForger = $forger;

        $this->assertNotSignedIn($this->signIn());
    }

    private function assertNotSignedIn(Response $response): void
    {
        self::assertSame('/', parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH));
        self::assertNull($this->securityToken());
        self::assertSame([], $this->events()->of(UserSignedIn::class));
    }
}
