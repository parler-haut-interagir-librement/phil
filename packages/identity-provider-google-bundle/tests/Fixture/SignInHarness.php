<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Fixture;

use Phil\IdentityProviderGoogleBundle\Application\Contract\SubjectHolds;
use Phil\IdentityProviderGoogleBundle\Application\GoogleClaims;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSettings;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSignIn;
use Phil\IdentityProviderGoogleBundle\Application\SignInAttempts;
use Phil\Url\Url;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretBasic;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * A sign-in with the local Google, as the bundle's services build it, with a session and a clock at `FakeGoogle::NOW`.
 */
final readonly class SignInHarness
{
    public FakeGoogle $google;

    public MockClock $clock;

    public Session $session;

    public RecordingLogger $logger;

    public GoogleSignIn $signIn;

    public function __construct(GoogleSettings $settings = new GoogleSettings(FakeGoogle::CLIENT_ID), ?SubjectHolds $holds = null, bool $logging = true)
    {
        $this->google = new FakeGoogle();
        $this->clock = new MockClock('@'.FakeGoogle::NOW);
        $this->session = new Session(new MockArraySessionStorage());
        $this->logger = new RecordingLogger();
        $transport = $this->google->transport();
        $cache = new ArrayAdapter();
        $discovery = new OidcDiscovery($transport, $cache, GoogleSettings::DISCOVERY_URL, GoogleSettings::ISSUER, 86_400, 'test.discovery', ['authorization_endpoint', 'token_endpoint', 'jwks_uri']);

        $this->signIn = new GoogleSignIn(
            $discovery,
            new OidcClient($transport, $discovery, FakeGoogle::CLIENT_ID, new ClientSecretBasic(FakeGoogle::CLIENT_SECRET)),
            new OidcSignatureVerifier($discovery, $cache, $transport, ['RS256'], 3600, true, $this->clock),
            new OidcIdToken($this->clock, $settings->clockSkew),
            $settings,
            new GoogleClaims($settings),
            new SignInAttempts(),
            $this->clock,
            $holds,
            $logging ? $this->logger : null,
        );
    }

    /**
     * Starts a sign-in, and gives the parameters of the authorization request.
     *
     * @return array<array-key, mixed>
     */
    public function start(string $callback = 'https://example.com/login/with/google/callback'): array
    {
        $url = $this->signIn->start($this->session, $callback);
        parse_str((string) Url::fromString($url)->query, $parameters);

        return $parameters;
    }

    /**
     * Starts a sign-in and has the local Google answer it with an ID token of these claims.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{state: string, nonce: string}
     */
    public function answerWith(array $overrides = []): array
    {
        $parameters = $this->start();
        $state = \is_string($parameters['state'] ?? null) ? $parameters['state'] : '';
        $nonce = \is_string($parameters['nonce'] ?? null) ? $parameters['nonce'] : '';
        $this->google->idToken = FakeGoogle::sign(FakeGoogle::claims($nonce, $overrides));

        return ['state' => $state, 'nonce' => $nonce];
    }
}
