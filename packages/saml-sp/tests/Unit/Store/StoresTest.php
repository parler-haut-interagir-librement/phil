<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Store;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\SamlSp\Message\PendingRequest;
use Phil\SamlSp\Store\CachePendingRequests;
use Phil\SamlSp\Store\CacheReplayGuard;
use Phil\SamlSp\Store\InMemoryPendingRequests;
use Phil\SamlSp\Store\InMemoryReplayGuard;
use Phil\SamlSp\Tests\Support\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(InMemoryReplayGuard::class)]
#[CoversClass(CacheReplayGuard::class)]
#[CoversClass(InMemoryPendingRequests::class)]
#[CoversClass(CachePendingRequests::class)]
#[CoversClass(PendingRequest::class)]
final class StoresTest extends TestCase
{
    public function testTheMemoryGuardRemembersUntilTheExpiry(): void
    {
        $clock = Stage::clock();
        $guard = new InMemoryReplayGuard($clock);
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertTrue($guard->firstUse('idp', 'a', $until));
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        self::assertTrue($guard->firstUse('other', 'a', $until));
        self::assertTrue($guard->firstUse('idp', 'b', $until));

        $clock->modify('2026-11-19 12:04:59 UTC');
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        $clock->modify('2026-11-19 12:05:00 UTC');
        self::assertTrue($guard->firstUse('idp', 'a', $until));
    }

    /**
     * @return iterable<string, array{array{string, string}, array{string, string}}>
     */
    public static function distinctAssertions(): iterable
    {
        yield 'the split of the issuer and the ID' => [['a', 'bc'], ['ab', 'c']];
        yield 'the length hides in the text' => [['0', '123456789ab'], ['123456789a', 'b']];
        yield 'a colon hides in the issuer' => [['0', 'aaaaaaaaa:z'], [':aaaaaaaaa', 'z']];
        yield 'two issuers of one length' => [['idp-1', 'a'], ['idp-2', 'a']];
    }

    /**
     * @param array{string, string} $first
     * @param array{string, string} $second
     */
    #[DataProvider('distinctAssertions')]
    public function testTwoAssertionsNeverShareAKey(array $first, array $second): void
    {
        $until = new DateTimeImmutable('2126-01-01 00:00:00 UTC');

        foreach ([new InMemoryReplayGuard(Stage::clock()), new CacheReplayGuard(new ArrayAdapter())] as $guard) {
            self::assertTrue($guard->firstUse($first[0], $first[1], $until));
            self::assertTrue($guard->firstUse($second[0], $second[1], $until));
            self::assertFalse($guard->firstUse($first[0], $first[1], $until));
        }
    }

    public function testTheCacheGuardKeepsATrueUnderTheKeyOfTheIssuerAndTheId(): void
    {
        $pool = new ArrayAdapter();
        new CacheReplayGuard($pool)->firstUse('idp', 'a', new DateTimeImmutable('+1 hour'));
        $item = $pool->getItem('phil_saml_sp.assertion.'.hash('sha256', '3:idpa'));

        self::assertTrue($item->isHit());
        self::assertTrue($item->get());
    }

    public function testTheCacheGuardRemembersInThePool(): void
    {
        $pool = new ArrayAdapter();
        $guard = new CacheReplayGuard($pool);
        $until = new DateTimeImmutable('+1 hour');

        self::assertTrue($guard->firstUse('idp', 'a', $until));
        self::assertFalse(new CacheReplayGuard($pool)->firstUse('idp', 'a', $until));
        self::assertTrue($guard->firstUse('idp', 'b', $until));
        self::assertTrue($guard->firstUse("idp\na", '', $until) && $guard->firstUse('idp', "a\n", $until));
        self::assertTrue($pool->hasItem('phil_saml_sp.assertion.'.hash('sha256', "5:idp\na")));
    }

    public function testTheCacheGuardForgetsAtTheExpiry(): void
    {
        $pool = new ArrayAdapter();
        $guard = new CacheReplayGuard($pool);

        self::assertTrue($guard->firstUse('idp', 'a', new DateTimeImmutable('-1 second')));
        self::assertTrue($guard->firstUse('idp', 'a', new DateTimeImmutable('+1 hour')));
    }

    public function testTheMemoryRequestsAreTakenOnce(): void
    {
        $requests = new InMemoryPendingRequests();
        $request = new PendingRequest('_a', 'acme', new DateTimeImmutable());
        $requests->save($request);

        self::assertSame($request, $requests->take('_a'));
        self::assertNull($requests->take('_a'));
        self::assertNull($requests->take('_b'));
    }

    public function testTheCacheRequestsAreTakenOnce(): void
    {
        $pool = new ArrayAdapter();
        $requests = new CachePendingRequests($pool);
        $requests->save(new PendingRequest('_a', 'acme', new DateTimeImmutable('2026-11-19 12:00:00 UTC'), '/courses', 'hash'));

        $request = new CachePendingRequests($pool)->take('_a');
        self::assertNotNull($request);
        self::assertSame(['id' => '_a', 'idp' => 'acme', 'created' => 1_795_089_600, 'target' => '/courses', 'binding' => 'hash'], $request->toArray());
        self::assertNull($requests->take('_a'));
        self::assertNull($requests->take('_b'));
    }

    public function testTheCacheRequestsWaitTenMinutesByDefault(): void
    {
        $pool = new ArrayAdapter();
        new CachePendingRequests($pool)->save(new PendingRequest('_a', 'acme', new DateTimeImmutable()));
        $expiries = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($pool);
        self::assertIsArray($expiries);

        self::assertEqualsWithDelta(microtime(true) + 600, array_first($expiries), 0.9);
    }

    public function testAMissingRequestIsNotDeleted(): void
    {
        $item = self::createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $pool = self::createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects(self::never())->method('deleteItem');

        self::assertNull(new CachePendingRequests($pool)->take('_a'));
    }

    public function testTheCacheRequestsExpire(): void
    {
        $pool = new ArrayAdapter();
        new CachePendingRequests($pool, 60)->save(new PendingRequest('_a', 'acme', new DateTimeImmutable()));
        $expiries = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($pool);
        self::assertIsArray($expiries);

        self::assertEqualsWithDelta(microtime(true) + 60, array_first($expiries), 5);
    }

    public function testACorruptedEntryIsNoRequest(): void
    {
        $pool = new ArrayAdapter();
        $pool->save($pool->getItem('phil_saml_sp.request.'.hash('sha256', '_a'))->set('nope'));
        $pool->save($pool->getItem('phil_saml_sp.request.'.hash('sha256', '_b'))->set(new PendingRequest('_c', 'acme', new DateTimeImmutable())->toArray()));

        self::assertNull(new CachePendingRequests($pool)->take('_a'));
        self::assertNull(new CachePendingRequests($pool)->take('_b'));
        self::assertFalse($pool->hasItem('phil_saml_sp.request.'.hash('sha256', '_b')));
    }

    public function testTheLifetimeIsBounded(): void
    {
        self::assertNotNull(new CachePendingRequests(new ArrayAdapter(), 60));
        self::assertNotNull(new CachePendingRequests(new ArrayAdapter(), 3600));

        foreach ([59, 3601] as $lifetime) {
            try {
                new CachePendingRequests(new ArrayAdapter(), $lifetime);
                self::fail('The lifetime was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(\sprintf('A request waits 60 to 3600 seconds for its response; %d is not.', $lifetime), $exception->getMessage());
            }
        }
    }

    public function testAPendingRequestFromAnArray(): void
    {
        $request = PendingRequest::fromArray(['id' => '_a', 'idp' => 'acme', 'created' => 10]);

        self::assertNotNull($request);
        self::assertNull($request->target);
        self::assertNull($request->browserBinding);
        self::assertSame(10, $request->createdAt->getTimestamp());
        self::assertNull(PendingRequest::fromArray(['id' => '_a', 'idp' => 'acme', 'created' => 10, 'target' => 3, 'binding' => []])?->target);
        self::assertNull(PendingRequest::fromArray(['id' => '_a', 'idp' => 'acme', 'created' => 10, 'binding' => 1])?->browserBinding);

        foreach ([null, [], ['id' => 1, 'idp' => 'a', 'created' => 1], ['id' => 'a', 'idp' => 1, 'created' => 1], ['id' => 'a', 'idp' => 'b', 'created' => '1']] as $data) {
            self::assertNull(PendingRequest::fromArray($data));
        }
    }

    public function testAPendingRequestHasAnIdAndAnIdentityProvider(): void
    {
        foreach ([['', 'acme'], ['_a', '']] as [$id, $idp]) {
            try {
                new PendingRequest($id, $idp, new DateTimeImmutable());
                self::fail('The request was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('A pending request has an ID and an identity provider.', $exception->getMessage());
            }
        }
    }
}
