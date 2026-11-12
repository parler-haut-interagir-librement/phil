<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\IdentityClientBundle\Application\Event\ProviderSessionEnded;
use Phil\IdentityClientBundle\Infrastructure\Security\ReplayGuardedBackChannelLogout;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;
use Symfony\Component\Security\Http\Oidc\OidcLogoutToken;

#[CoversClass(ReplayGuardedBackChannelLogout::class)]
final class ReplayGuardedBackChannelLogoutTest extends TestCase
{
    private MockClock $clock;

    private FakeOidcProvider $provider;

    private OidcEndedSessions $ended;

    private SpyDispatcher $dispatcher;

    private SpyReplayStore $replays;

    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->provider = new FakeOidcProvider($this->clock);
        $this->ended = new OidcEndedSessions(new ArrayAdapter(), 'main', 3600);
        $this->dispatcher = new SpyDispatcher();
        $this->replays = new SpyReplayStore();
        $this->logger = new RecordingLogger();
    }

    public function testAnAcceptedTokenEndsTheProviderSession(): void
    {
        $this->logout($this->provider->logoutToken(['jti' => 'j1']));

        self::assertTrue($this->ended->has(FakeOidcProvider::SESSION_ID));
        self::assertEquals([new ProviderSessionEnded(FakeOidcProvider::SESSION_ID)], $this->dispatcher->events);
        self::assertSame([['j1', 86400]], $this->replays->claims);
    }

    public function testAClaimOutlivesAnAcceptedLongToken(): void
    {
        $this->logout($this->provider->logoutToken(['exp' => $this->clock->now()->getTimestamp() + 90000]));

        self::assertSame(90001, $this->replays->claims[0][1]);
    }

    public function testAFractionalExpiryIsAccepted(): void
    {
        $this->logout($this->provider->logoutToken(['exp' => $this->clock->now()->getTimestamp() + 90000.5]));

        self::assertSame(90001, $this->replays->claims[0][1]);
    }

    public function testAReplayEndsNothing(): void
    {
        $this->replays->answer = false;

        try {
            $this->logout($this->provider->logoutToken());
            self::fail('A replay must be refused.');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('The logout token was already used.', $authenticationException->getMessage());
            self::assertSame(['A back-channel logout token was presented again.'], $this->logger->messages);
        }

        self::assertFalse($this->ended->has(FakeOidcProvider::SESSION_ID));
        self::assertSame([], $this->dispatcher->events);
    }

    public function testASignatureFailureIsAnsweredWithoutItsDetail(): void
    {
        try {
            $this->logout(FakeOidcProvider::encode(['alg' => 'none'], ['sid' => 'x']));
            self::fail('An unsigned token must be refused.');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('The logout token could not be verified.', $authenticationException->getMessage());
            self::assertInstanceOf(AuthenticationException::class, $authenticationException->getPrevious());
            self::assertSame(0, $authenticationException->getCode());
            self::assertSame(['The back-channel logout token could not be verified.'], $this->logger->messages);
        }

        self::assertSame([], $this->replays->claims);
    }

    public function testInvalidClaimsAreRefusedBeforeTheStoreIsTouched(): void
    {
        try {
            $this->logout($this->provider->logoutToken(['aud' => 'another']));
            self::fail('A foreign audience must be refused.');
        } catch (AuthenticationException $authenticationException) {
            self::assertStringStartsWith('Invalid logout token: ', $authenticationException->getMessage());
            self::assertSame([], $this->replays->claims);
            self::assertSame(['The back-channel logout token was refused.'], $this->logger->messages);
        }
    }

    public function testATextualJtiIsRequired(): void
    {
        foreach ([7, ''] as $jti) {
            try {
                $this->logout($this->provider->logoutToken(['jti' => $jti]));
                self::fail('A malformed jti must be refused.');
            } catch (AuthenticationException $exception) {
                self::assertSame('Invalid logout token: the "jti" claim must be a non-empty string.', $exception->getMessage());
            }
        }

        self::assertSame([], $this->replays->claims);
        self::assertSame(['The back-channel logout token was refused.', 'The back-channel logout token was refused.'], $this->logger->messages);
    }

    private function logout(string $token): void
    {
        $client = new MockHttpClient($this->provider);
        $discovery = new OidcDiscovery($client, new ArrayAdapter(), '.well-known/openid-configuration', FakeOidcProvider::ISSUER);

        new ReplayGuardedBackChannelLogout(
            new OidcSignatureVerifier($discovery, new ArrayAdapter(), $client, ['RS256'], 3600, true, $this->clock),
            new OidcLogoutToken($this->clock),
            $discovery,
            FakeOidcProvider::CLIENT_ID,
            $this->ended,
            $this->replays,
            $this->dispatcher,
            $this->clock,
            $this->logger,
            86400,
        )->logout($token);
    }
}
