<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\IdentityClientBundle\Application\Contract\RefreshOutcomes;
use Phil\IdentityClientBundle\Application\Event\RefreshTokenRejected;
use Phil\IdentityClientBundle\Application\Event\TokensRefreshed;
use Phil\IdentityClientBundle\Application\Refresh\RefreshOutcome;
use Phil\IdentityClientBundle\Application\Refresh\RefreshStatus;
use Phil\IdentityClientBundle\Infrastructure\Security\SerializedTokenRefreshListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use Phil\IdentityClientBundle\Infrastructure\Store\EncryptedRefreshOutcomes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokenRefresher;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

#[CoversClass(SerializedTokenRefreshListener::class)]
final class SerializedTokenRefreshListenerTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private MockClock $clock;

    private TokenStorage $storage;

    private RefreshOutcomes $outcomes;

    private SpyDispatcher $dispatcher;

    private InMemoryStore $store;

    private FakeOidcClient $client;

    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('@'.self::NOW));
        $this->storage = new TokenStorage();
        $this->outcomes = new EncryptedRefreshOutcomes(new ArrayAdapter(), 120);
        $this->dispatcher = new SpyDispatcher();
        $this->store = new InMemoryStore();
        $this->client = new FakeOidcClient();
        $this->logger = new RecordingLogger();
    }

    public function testSupportsDefersToAuthenticate(): void
    {
        self::assertNull($this->listener()->supports(new Request()));
    }

    public function testNoTokenNothingHappens(): void
    {
        $this->listener()->authenticate($this->event());

        self::assertSame([], $this->client->presented);
    }

    public function testATokenWithoutRefreshTokenIsLeftAlone(): void
    {
        $this->storage->setToken(TokenFixture::token([TokenAttribute::ACCESS_TOKEN_EXPIRES_AT => self::NOW]));

        $this->listener()->authenticate($this->event());

        self::assertSame([], $this->client->presented);
    }

    public function testATokenWithoutExpiryIsLeftAlone(): void
    {
        $this->storage->setToken(TokenFixture::token([TokenAttribute::REFRESH_TOKEN => 'rt1']));

        $this->listener()->authenticate($this->event());

        self::assertSame([], $this->client->presented);
    }

    public function testATokenOutsideTheLeewayIsLeftAlone(): void
    {
        $this->storage->setToken($this->expiring(self::NOW + 31));

        $this->listener()->authenticate($this->event());

        self::assertSame([], $this->client->presented);
    }

    public function testATokenAtTheLeewayIsRenewed(): void
    {
        $token = $this->expiring(self::NOW + 30);
        $this->storage->setToken($token);

        $this->listener()->authenticate($this->event());

        self::assertSame(['rt1'], $this->client->presented);
        self::assertSame('at2', $token->getAttribute(TokenAttribute::ACCESS_TOKEN));
        self::assertSame('rt2', $token->getAttribute(TokenAttribute::REFRESH_TOKEN));
        self::assertCount(1, $this->dispatcher->events);
        self::assertInstanceOf(TokensRefreshed::class, $this->dispatcher->events[0]);
        self::assertSame('subject-1', $this->dispatcher->events[0]->subject);
    }

    public function testAStaleRequestAppliesTheRotationAndNeverPresentsTheOldTokenAgain(): void
    {
        $winner = $this->expiring(self::NOW);
        $this->storage->setToken($winner);
        $this->listener()->authenticate($this->event());

        $stale = $this->expiring(self::NOW);
        $this->storage->setToken($stale);
        $this->listener()->authenticate($this->event());

        self::assertSame(['rt1'], $this->client->presented);
        self::assertSame('at2', $stale->getAttribute(TokenAttribute::ACCESS_TOKEN));
        self::assertSame('rt2', $stale->getAttribute(TokenAttribute::REFRESH_TOKEN));
        self::assertSame(self::NOW + 300, $stale->getAttribute(TokenAttribute::ACCESS_TOKEN_EXPIRES_AT));
        self::assertCount(1, $this->dispatcher->events);
    }

    public function testTheLockIsReleasedAfterwards(): void
    {
        $this->storage->setToken($this->expiring(self::NOW));

        $this->listener()->authenticate($this->event());

        self::assertTrue(new LockFactory($this->store)->createLock('phil_identity_client.refresh.'.hash('sha256', 'rt1'))->acquire(false));
    }

    public function testTheLockIsReleasedWhenTheStoreFails(): void
    {
        $this->outcomes = new FailingRefreshOutcomes();
        $this->storage->setToken($this->expiring(self::NOW));

        try {
            $this->listener()->authenticate($this->event());
            self::fail('The store fails.');
        } catch (RuntimeException) {
            self::assertTrue(new LockFactory($this->store)->createLock('phil_identity_client.refresh.'.hash('sha256', 'rt1'))->acquire(false));
        }
    }

    public function testOnlyTheRefreshedAttributesAreShared(): void
    {
        $token = $this->expiring(self::NOW);
        $token->setAttribute('unrelated', 'private');

        $this->storage->setToken($token);

        $this->listener()->authenticate($this->event());

        $found = $this->outcomes->find('rt1');
        self::assertNotNull($found);
        self::assertArrayNotHasKey('unrelated', $found->attributes);
        self::assertSame('rt2', $found->attributes[TokenAttribute::REFRESH_TOKEN]);
    }

    public function testARevokedFamilyEndsTheSession(): void
    {
        $this->client->failure = new OidcInvalidGrantException('gone');
        $this->storage->setToken($this->expiring(self::NOW));

        $this->listener()->authenticate($this->event());

        self::assertNull($this->storage->getToken());
        self::assertCount(1, $this->dispatcher->events);
        self::assertInstanceOf(RefreshTokenRejected::class, $this->dispatcher->events[0]);
        self::assertSame('subject-1', $this->dispatcher->events[0]->subject);
    }

    public function testAStaleRequestSharesTheRevocationWithoutCallingTheProvider(): void
    {
        $this->outcomes->publish('rt1', new RefreshOutcome(RefreshStatus::Revoked));
        $this->storage->setToken($this->expiring(self::NOW));

        $this->listener()->authenticate($this->event());

        self::assertNull($this->storage->getToken());
        self::assertSame([], $this->client->presented);
    }

    public function testARevocationInvalidatesTheWholeSession(): void
    {
        $this->client->failure = new OidcInvalidGrantException('gone');
        $this->storage->setToken($this->expiring(self::NOW));
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', 'kept until now');

        $before = $session->getId();
        $request = new Request();
        $request->setSession($session);

        $this->listener()->authenticate(new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertNull($this->storage->getToken());
        self::assertFalse($session->has('cart'));
        self::assertNotSame($before, $session->getId());
    }

    public function testARefreshLeavesTheSessionAlone(): void
    {
        $this->storage->setToken($this->expiring(self::NOW));
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', 'kept');

        $request = new Request();
        $request->setSession($session);

        $this->listener()->authenticate(new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('kept', $session->get('cart'));
    }

    public function testAnUnavailableProviderLeavesTheTokensAndIsRemembered(): void
    {
        $this->client->failure = new AuthenticationException('down');
        $token = $this->expiring(self::NOW);
        $this->storage->setToken($token);

        $this->listener()->authenticate($this->event());
        $this->listener()->authenticate($this->event());

        self::assertSame($token, $this->storage->getToken());
        self::assertSame('rt1', $token->getAttribute(TokenAttribute::REFRESH_TOKEN));
        self::assertSame(['rt1'], $this->client->presented);
        self::assertSame([], $this->dispatcher->events);
        self::assertSame(['Could not renew the tokens of the session; they stay as they are.'], $this->logger->messages);
        self::assertSame(RefreshStatus::Unavailable, $this->outcomes->find('rt1')?->status);
    }

    public function testARequestWaitsForTheResultOfTheOneUnderWay(): void
    {
        $holder = new LockFactory($this->store)->createLock('phil_identity_client.refresh.'.hash('sha256', 'rt1'));
        $holder->acquire();

        $clock = new class($this->clock, function () use ($holder): void {
            $this->outcomes->publish('rt1', new RefreshOutcome(RefreshStatus::Refreshed, [TokenAttribute::ACCESS_TOKEN => 'from-the-winner']));
            $holder->release();
        }) implements ClockInterface {
            public int $sleeps = 0;

            public float $slept = 0.0;

            public function __construct(private readonly MockClock $inner, private readonly Closure $onSecondSleep)
            {
            }

            #[Override]
            public function now(): DatePoint
            {
                return $this->inner->now();
            }

            #[Override]
            public function sleep(float|int $seconds): void
            {
                ++$this->sleeps;
                $this->slept = (float) $seconds;
                $this->inner->sleep($seconds);
                if (2 === $this->sleeps) {
                    ($this->onSecondSleep)();
                }
            }

            #[Override]
            public function withTimeZone(DateTimeZone|string $timezone): static
            {
                return $this;
            }
        };
        $token = $this->expiring(self::NOW);
        $this->storage->setToken($token);

        $this->listener($clock)->authenticate($this->event());

        self::assertSame(2, $clock->sleeps);
        self::assertSame(0.05, $clock->slept);
        self::assertSame([], $this->client->presented);
        self::assertSame('from-the-winner', $token->getAttribute(TokenAttribute::ACCESS_TOKEN));
    }

    public function testARequestGivesUpAfterTheTimeout(): void
    {
        $holder = new LockFactory($this->store)->createLock('phil_identity_client.refresh.'.hash('sha256', 'rt1'));
        $holder->acquire();

        $token = $this->expiring(self::NOW);
        $this->storage->setToken($token);

        $this->listener(waitTimeout: 0.1)->authenticate($this->event());

        self::assertSame([], $this->client->presented);
        self::assertSame('rt1', $token->getAttribute(TokenAttribute::REFRESH_TOKEN));
        self::assertSame(['Gave up waiting for the refresh of the session tokens; it is tried again on the next request.'], $this->logger->messages);
        self::assertEqualsWithDelta(self::NOW + 0.1, (float) $this->clock->now()->format('U.u'), 0.001);
        self::assertTrue($holder->isAcquired(), 'The lock of another request is not released.');
    }

    public function testTheLockLivesForItsTtl(): void
    {
        $seen = null;
        $store = new class($this->store, $seen) implements PersistingStoreInterface {
            public function __construct(private readonly InMemoryStore $inner, public ?float &$seen)
            {
            }

            #[Override]
            public function save(Key $key): void
            {
                $this->inner->save($key);
            }

            #[Override]
            public function delete(Key $key): void
            {
                $this->inner->delete($key);
            }

            #[Override]
            public function exists(Key $key): bool
            {
                return $this->inner->exists($key);
            }

            #[Override]
            public function putOffExpiration(Key $key, float $ttl): void
            {
                $this->seen = $ttl;
                $this->inner->putOffExpiration($key, $ttl);
            }
        };
        $this->storage->setToken($this->expiring(self::NOW));

        $this->listener(lockFactory: new LockFactory($store))->authenticate($this->event());

        self::assertSame(30.0, $seen);
    }

    private function expiring(int $expiresAt): PostAuthenticationToken
    {
        return TokenFixture::token([TokenAttribute::REFRESH_TOKEN => 'rt1', TokenAttribute::ACCESS_TOKEN => 'at1', TokenAttribute::ACCESS_TOKEN_EXPIRES_AT => $expiresAt]);
    }

    private function event(): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
    }

    private function listener(?ClockInterface $clock = null, float $waitTimeout = 10.0, ?LockFactory $lockFactory = null): SerializedTokenRefreshListener
    {
        $logger = $this->logger;
        $discovery = new OidcDiscovery(new MockHttpClient(), new ArrayAdapter(), 'https://id.example.test/.well-known/openid-configuration');
        $refresher = new OidcTokenRefresher($this->client, $discovery, new OidcIdToken($this->clock), 'client', null, 30, $this->clock);

        return new SerializedTokenRefreshListener(
            $this->storage,
            $refresher,
            $lockFactory ?? new LockFactory($this->store),
            $this->outcomes,
            $this->dispatcher,
            $clock ?? $this->clock,
            $logger,
            30,
            $waitTimeout,
            30.0,
        );
    }
}
