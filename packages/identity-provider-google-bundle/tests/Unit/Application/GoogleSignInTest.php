<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSettings;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSignIn;
use Phil\IdentityProviderGoogleBundle\Application\SignInAttempts;
use Phil\IdentityProviderGoogleBundle\Infrastructure\Cache\CacheSubjectHolds;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\FakeGoogle;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\SignInHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[CoversClass(GoogleSignIn::class)]
final class GoogleSignInTest extends TestCase
{
    public function testTheAuthorizationRequestCarriesAStateANonceAndAPkceChallenge(): void
    {
        $harness = new SignInHarness();
        $url = $harness->signIn->start($harness->session, 'https://example.com/login/with/google/callback');

        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=1234-test.apps.googleusercontent.com&redirect_uri=https%3A%2F%2Fexample.com%2Flogin%2Fwith%2Fgoogle%2Fcallback&scope=openid%20email&state=', $url);
        parse_str(substr($url, (int) strpos($url, '?') + 1), $parameters);

        self::assertSame(['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'nonce', 'code_challenge', 'code_challenge_method', 'access_type', 'prompt'], array_keys($parameters));
        self::assertSame('S256', $parameters['code_challenge_method']);
        self::assertSame('online', $parameters['access_type']);
        self::assertSame('select_account', $parameters['prompt']);

        foreach (['state', 'nonce', 'code_challenge'] as $random) {
            self::assertIsString($parameters[$random]);
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $parameters[$random]);
        }

        self::assertNotSame($parameters['state'], $parameters['nonce']);
        $held = $harness->session->get(SignInAttempts::PREFIX.$parameters['state']);
        self::assertIsArray($held);
        self::assertSame($parameters['nonce'], $held['nonce']);
        self::assertSame('https://example.com/login/with/google/callback', $held['redirect_uri']);
        self::assertSame(FakeGoogle::NOW, $held['started_at']);
        self::assertIsString($held['code_verifier']);
        self::assertSame($parameters['code_challenge'], sodium_bin2base64(hash('sha256', $held['code_verifier'], true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
        self::assertNotSame($parameters['state'], $held['code_verifier']);
    }

    public function testTheProfileThePromptAndTheHostedDomainFollowTheSettings(): void
    {
        $parameters = new SignInHarness(new GoogleSettings(FakeGoogle::CLIENT_ID, ['example.com'], true, null))->start();

        self::assertSame('openid email profile', $parameters['scope']);
        self::assertSame('example.com', $parameters['hd']);
        self::assertArrayNotHasKey('prompt', $parameters);
    }

    public function testAnAuthorizationEndpointWithAQueryKeepsIt(): void
    {
        $harness = new SignInHarness();
        $harness->google->answers[FakeGoogle::DISCOVERY] = new MockResponse((string) json_encode(['authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth?flow=x'] + (array) json_decode(FakeGoogle::fixture('google-openid-configuration.json'), true)), ['response_headers' => ['content-type: application/json']]);

        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?flow=x&response_type=code&', $harness->signIn->start($harness->session, 'https://example.com/cb'));
    }

    public function testAnAuthorizationEndpointThatIsNotAnAddressStopsTheStart(): void
    {
        $harness = new SignInHarness();
        $harness->google->answers[FakeGoogle::DISCOVERY] = new MockResponse((string) json_encode(['authorization_endpoint' => 'https://exa mple.com/auth'] + (array) json_decode(FakeGoogle::fixture('google-openid-configuration.json'), true)), ['response_headers' => ['content-type: application/json']]);

        try {
            $harness->signIn->start($harness->session, 'https://example.com/cb');
            self::fail('The start went on.');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('Google announces an authorization endpoint that is not an address.', $authenticationException->getMessage());
            self::assertNotNull($authenticationException->getPrevious());
        }

        self::assertSame([], $harness->session->all());
    }

    public function testTheAnswerGivesTheIdentityAndTheCodeIsExchangedWithTheVerifier(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith(['iss' => 'accounts.google.com']);
        $verifier = $harness->session->get(SignInAttempts::PREFIX.$sent['state']);
        self::assertIsArray($verifier);

        $identity = $harness->signIn->finish($harness->session, ['state' => $sent['state'], 'code' => '4/a-code', 'iss' => 'https://accounts.google.com', 'scope' => 'email openid']);

        self::assertSame('google', $identity->provider);
        self::assertSame(FakeGoogle::SUBJECT, $identity->subject);
        self::assertSame('ada.lovelace@gmail.com', $identity->email);
        self::assertTrue($identity->emailVerified);
        self::assertNull($identity->displayName);
        self::assertSame([], $harness->session->all());

        $token = array_find($harness->google->requests, static fn (array $request): bool => FakeGoogle::TOKEN === $request['url']);
        self::assertNotNull($token);
        self::assertSame('POST', $token['method']);
        parse_str($token['body'], $body);
        self::assertSame(['client_id' => FakeGoogle::CLIENT_ID, 'grant_type' => 'authorization_code', 'code' => '4/a-code', 'redirect_uri' => 'https://example.com/login/with/google/callback', 'code_verifier' => $verifier['code_verifier']], $body);
        self::assertContains('Authorization: Basic '.base64_encode(urlencode(FakeGoogle::CLIENT_ID).':'.urlencode(FakeGoogle::CLIENT_SECRET)), $token['headers']);
        self::assertSame([], $harness->logger->records);
    }

    public function testACallbackIsAcceptedOnce(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();
        $harness->signIn->finish($harness->session, ['state' => $sent['state'], 'code' => 'c']);

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c'], 'no sign-in waits for this state');
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function badCallbacks(): iterable
    {
        yield 'no state' => [['code' => 'c'], 'no sign-in waits for this state'];
        yield 'an empty state' => [['state' => '', 'code' => 'c'], 'no sign-in waits for this state'];
        yield 'a state that is not a string' => [['state' => ['x'], 'code' => 'c'], 'no sign-in waits for this state'];
        yield 'another state' => [['state' => 'another', 'code' => 'c'], 'no sign-in waits for this state'];
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[DataProvider('badCallbacks')]
    public function testACallbackOfNoAttemptIsRefused(array $parameters, string $cause): void
    {
        $harness = new SignInHarness();
        $harness->answerWith();

        $this->expectRefusal($harness, $parameters, $cause);
    }

    public function testAnErrorOfGoogleIsRefusedAndConsumesTheAttempt(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();

        $this->expectRefusal($harness, ['state' => $sent['state'], 'error' => 'access_denied'], 'Google answered with an error');
        self::assertSame([], $harness->session->all());
        self::assertCount(1, $harness->google->requests);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function noCodes(): iterable
    {
        yield 'none' => [[]];
        yield 'empty' => [['code' => '']];
        yield 'not a string' => [['code' => ['c']]];
    }

    /**
     * @param array<array-key, mixed> $code
     */
    #[DataProvider('noCodes')]
    public function testAnAnswerWithoutACodeIsRefused(array $code): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();

        $this->expectRefusal($harness, ['state' => $sent['state']] + $code, 'the answer has no code');
    }

    public function testASignInThatWaitedTooLongIsRefused(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();
        $harness->clock->modify('+601 seconds');

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c'], 'the sign-in waited too long');
    }

    public function testASignInAnsweredAtTheEndOfItsLifetimeIsAccepted(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith(['iat' => FakeGoogle::NOW + 600, 'exp' => FakeGoogle::NOW + 4200]);
        $harness->clock->modify('+600 seconds');

        self::assertSame(FakeGoogle::SUBJECT, $harness->signIn->finish($harness->session, ['state' => $sent['state'], 'code' => 'c'])->subject);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function otherIssuers(): iterable
    {
        yield 'another issuer' => ['https://evil.example'];
        yield 'not a string' => [['https://accounts.google.com']];
    }

    #[DataProvider('otherIssuers')]
    public function testAnAnswerNamingAnotherIssuerIsRefused(mixed $issuer): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c', 'iss' => $issuer], 'The answer names another issuer');
        self::assertCount(1, $harness->google->requests);
    }

    public function testAnIssuerParameterWithATrailingSlashIsGoogle(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();

        self::assertSame(FakeGoogle::SUBJECT, $harness->signIn->finish($harness->session, ['state' => $sent['state'], 'code' => 'c', 'iss' => 'https://accounts.google.com/'])->subject);
    }

    public function testATokenResponseWithoutAnIdTokenIsRefused(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();
        $harness->google->idToken = null;

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c'], 'The token endpoint answered without an ID token');
    }

    public function testAnEmptyIdTokenIsRefused(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();
        $harness->google->idToken = '';

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c'], 'The token endpoint answered without an ID token');
    }

    public function testAnIdTokenSignedByAnotherKeyIsRefused(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith();
        $harness->google->idToken = FakeGoogle::sign(FakeGoogle::claims($sent['nonce']), otherKey: true);

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c'], 'The ID token signature is invalid');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badClaims(): iterable
    {
        yield 'another nonce' => [['nonce' => 'another'], 'ID token nonce does not match'];
        yield 'another audience' => [['aud' => 'other.apps.googleusercontent.com', 'azp' => null], 'Invalid ID token: "Bad audience."'];
        yield 'another authorized party' => [['azp' => 'other.apps.googleusercontent.com'], 'ID token "azp" claim does not match the expected client_id'];
        yield 'another issuer' => [['iss' => 'https://evil.example'], 'Invalid ID token: "Unknown issuer."'];
        yield 'expired' => [['exp' => FakeGoogle::NOW - 61], 'Invalid ID token: "The token expired."'];
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[DataProvider('badClaims')]
    public function testAnIdTokenWithBadClaimsIsRefused(array $claims, string $cause): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith($claims);

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c'], $cause);
    }

    public function testTheClockSkewOfTheSettingsIsAllowed(): void
    {
        $harness = new SignInHarness();
        $sent = $harness->answerWith(['exp' => FakeGoogle::NOW - 59]);

        self::assertSame(FakeGoogle::SUBJECT, $harness->signIn->finish($harness->session, ['state' => $sent['state'], 'code' => 'c'])->subject);
    }

    public function testAHeldSubjectIsRefused(): void
    {
        $holds = new CacheSubjectHolds(new ArrayAdapter());
        $holds->hold('google', FakeGoogle::SUBJECT);

        $harness = new SignInHarness(holds: $holds);
        $sent = $harness->answerWith();

        $this->expectRefusal($harness, ['state' => $sent['state'], 'code' => 'c'], 'Google disabled this account as hijacked');
    }

    public function testASubjectHeldUnderAnotherProviderIsNotRefused(): void
    {
        $holds = new CacheSubjectHolds(new ArrayAdapter());
        $holds->hold('other', FakeGoogle::SUBJECT);

        $harness = new SignInHarness(holds: $holds);
        $sent = $harness->answerWith();

        self::assertSame(FakeGoogle::SUBJECT, $harness->signIn->finish($harness->session, ['state' => $sent['state'], 'code' => 'c'])->subject);
    }

    public function testAnAccountOfAnotherDomainIsRefusedByTheClaims(): void
    {
        $harness = new SignInHarness(new GoogleSettings(FakeGoogle::CLIENT_ID, ['example.com']));
        $sent = $harness->answerWith(['hd' => 'other.example']);

        try {
            $harness->signIn->finish($harness->session, ['state' => $sent['state'], 'code' => 'c']);
            self::fail('The identity was accepted.');
        } catch (Refused $refused) {
            self::assertSame(['external.failed'], $refused->reasons);
        }
    }

    public function testARefusalWithoutALoggerIsStillARefusal(): void
    {
        $harness = new SignInHarness(logging: false);

        try {
            $harness->signIn->finish($harness->session, ['state' => 'unknown', 'code' => 'c']);
            self::fail('The answer was accepted.');
        } catch (Refused $refused) {
            self::assertSame(['external.failed'], $refused->reasons);
        }

        self::assertSame([], $harness->logger->records);
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    private function expectRefusal(SignInHarness $harness, array $parameters, string $cause): void
    {
        try {
            $harness->signIn->finish($harness->session, $parameters);
            self::fail('The answer was accepted.');
        } catch (Refused $refused) {
            self::assertSame(['external.failed'], $refused->reasons);
        }

        self::assertCount(1, $harness->logger->records);
        self::assertSame('info', $harness->logger->records[0]['level']);
        self::assertStringStartsWith('A sign-in with Google was refused: '.$cause, $harness->logger->records[0]['message']);
        self::assertStringEndsWith('.', $harness->logger->records[0]['message']);
    }
}
