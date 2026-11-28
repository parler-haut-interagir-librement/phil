<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;
use Phil\SitesConsoleBundle\Infrastructure\Cache\CacheConsoleKeyStore;
use Phil\SitesConsoleBundle\Infrastructure\Cache\CacheNonceStore;
use Phil\SitesConsoleBundle\Infrastructure\Http\BoundedBody;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RefusingPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The agent's stores and its body reader on their own: a nonce accepted once, even by two servers at the same time;
 * nothing accepted that the pool did not keep; a body never read past its cap.
 */
#[CoversClass(CacheNonceStore::class)]
#[CoversClass(CacheConsoleKeyStore::class)]
#[CoversClass(StateNotKept::class)]
#[CoversClass(BoundedBody::class)]
final class AgentStoresTest extends TestCase
{
    private const string NONCE_KEY_PREFIX = 'phil_sites_console.nonce.';

    public function testANonceIsAcceptedOnceForItsTime(): void
    {
        $clock = new MockClock();
        $pool = new ArrayAdapter(clock: $clock);
        $store = new CacheNonceStore($pool, new LockFactory(new InMemoryStore()));

        self::assertTrue($store->remember('nonce-a', 10));
        self::assertFalse($store->remember('nonce-a', 10));
        self::assertTrue($store->remember('nonce-b', 10));
        $clock->sleep(11);
        self::assertTrue($store->remember('nonce-a', 10));

        $item = $pool->getItem(self::NONCE_KEY_PREFIX.hash('xxh128', 'nonce-a'));
        self::assertTrue($item->isHit());
        self::assertTrue($item->get());
    }

    public function testANonceAnotherServerIsCheckingIsAReplay(): void
    {
        $locks = new InMemoryStore();
        $pool = new ArrayAdapter();
        $other = new LockFactory($locks)->createLock(self::NONCE_KEY_PREFIX.hash('xxh128', 'nonce-a'));
        self::assertTrue($other->acquire());

        $store = new CacheNonceStore($pool, new LockFactory($locks));

        self::assertFalse($store->remember('nonce-a', 10), 'The same nonce, at the same time, on another server.');
        self::assertSame([], $pool->getValues(), 'The replay writes nothing.');
        self::assertTrue($other->isAcquired(), 'The lock of the other server is left alone.');

        $other->release();
        self::assertTrue($store->remember('nonce-a', 10));
        self::assertFalse($store->remember('nonce-a', 10));
    }

    public function testTheLockOfANonceIsReleasedWhateverTheOutcome(): void
    {
        $locks = new InMemoryStore();
        $store = new CacheNonceStore(new ArrayAdapter(), new LockFactory($locks));
        $probe = new LockFactory($locks);

        $store->remember('nonce-a', 10);
        self::assertTrue($probe->createLock(self::NONCE_KEY_PREFIX.hash('xxh128', 'nonce-a'))->acquire(), 'Released once accepted.');

        $store->remember('nonce-b', 10);
        $store->remember('nonce-b', 10);
        self::assertTrue($probe->createLock(self::NONCE_KEY_PREFIX.hash('xxh128', 'nonce-b'))->acquire(), 'Released once refused.');

        $refusing = new CacheNonceStore(new RefusingPool(), new LockFactory($locks));

        try {
            $refusing->remember('nonce-c', 10);
            self::fail('A nonce not kept is not accepted.');
        } catch (StateNotKept) {
            self::assertTrue($probe->createLock(self::NONCE_KEY_PREFIX.hash('xxh128', 'nonce-c'))->acquire(), 'Released once failed.');
        }
    }

    public function testANonceThePoolDoesNotKeepFailsTheRequest(): void
    {
        $store = new CacheNonceStore(new RefusingPool(), new LockFactory(new InMemoryStore()));

        $this->expectExceptionObject(StateNotKept::nonce());
        $store->remember('nonce-a', 10);
    }

    public function testTheConsoleKeysAreKeptWithoutExpiry(): void
    {
        $clock = new MockClock();
        $pool = new ArrayAdapter(clock: $clock);
        $store = new CacheConsoleKeyStore($pool);

        self::assertNull($store->read());
        $store->write('state');
        $clock->sleep(86_400 * 365);
        self::assertSame('state', $store->read());
        self::assertSame('state', $pool->getItem(CacheConsoleKeyStore::ITEM)->get());

        $pool->save($pool->getItem(CacheConsoleKeyStore::ITEM)->set(42));
        self::assertNull($store->read(), 'Anything but a text is no state.');
    }

    public function testConsoleKeysThePoolDoesNotKeepFailTheRotation(): void
    {
        $this->expectExceptionObject(StateNotKept::consoleKeys());
        new CacheConsoleKeyStore(new RefusingPool())->write('state');
    }

    public function testTheMessagesOfAStateNotKept(): void
    {
        self::assertSame('The nonce of the request could not be kept.', StateNotKept::nonce()->getMessage());
        self::assertSame('The console keys could not be kept.', StateNotKept::consoleKeys()->getMessage());
    }

    public function testABodyUpToItsCapIsRead(): void
    {
        self::assertSame('', BoundedBody::read(Request::create('/', 'POST'), 10));
        self::assertSame('0123456789', BoundedBody::read(Request::create('/', 'POST', content: '0123456789'), 10));
        self::assertSame('0123456789', BoundedBody::read($this->declared('0123456789', '10'), 10));
        self::assertSame('012345678', BoundedBody::read($this->declared('012345678', '9'), 10));
        self::assertSame('01', BoundedBody::read($this->declared('01', '0010'), 10), 'A declared length with leading zeros.');
    }

    public function testABodyPastItsCapIsRefusedWhateverItsHeaderSays(): void
    {
        self::assertNull(BoundedBody::read(Request::create('/', 'POST', content: '01234567890'), 10), 'No header.');
        self::assertNull(BoundedBody::read($this->declared('01234567890', '3'), 10), 'A header that lies.');
        self::assertNull(BoundedBody::read($this->declared('01234567890', 'many'), 10), 'A malformed header.');
        self::assertNull(BoundedBody::read($this->declared('01234567890', '-1'), 10), 'A negative header.');
    }

    /**
     * @param non-empty-string $declared
     */
    #[DataProvider('declaredNotNumbers')]
    public function testAHeaderThatIsNotExactlyDigitsIsNotTakenForALength(string $declared): void
    {
        self::assertSame('short', BoundedBody::read($this->declared('short', $declared), 10));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function declaredNotNumbers(): iterable
    {
        yield 'digits then a letter' => ['99999x'];
        yield 'a space then digits' => [' 99999'];
        yield 'digits then a newline' => ["99999\n"];
    }

    /**
     * @param non-empty-string $declared
     */
    #[DataProvider('declaredTooLong')]
    public function testADeclaredLengthPastTheCapIsRefusedBeforeReading(string $declared): void
    {
        self::assertNull(BoundedBody::read($this->declared('short', $declared), 10));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function declaredTooLong(): iterable
    {
        yield 'one past' => ['11'];
        yield 'with leading zeros' => ['0011'];
        yield 'past an integer' => ['99999999999999999999999'];
    }

    public function testTheReaderReadsTheStreamOfTheRequest(): void
    {
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, str_repeat('a', 100));
        rewind($stream);

        self::assertNull(BoundedBody::read(new Request(content: $stream), 10));
        self::assertSame(11, ftell($stream), 'One byte past the cap is read, no more.');
    }

    public function testTheLockOfANonceIsReleasedByTheStoreItself(): void
    {
        $factory = new class(new InMemoryStore()) extends LockFactory {
            /** @var list<SharedLockInterface> */
            public array $created = [];

            public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
            {
                return $this->created[] = parent::createLock($resource, $ttl, $autoRelease);
            }
        };

        $store = new CacheNonceStore(new ArrayAdapter(), $factory);
        $store->remember('nonce-a', 10);
        $store->remember('nonce-a', 10);

        self::assertCount(2, $factory->created);

        foreach ($factory->created as $lock) {
            self::assertFalse($lock->isAcquired(), 'Released before the store answers, not when the lock is collected.');
        }

        try {
            new CacheNonceStore(new RefusingPool(), $factory)->remember('nonce-b', 10);
            self::fail('A nonce not kept is not accepted.');
        } catch (StateNotKept) {
            self::assertFalse(array_last($factory->created)->isAcquired());
        }
    }

    private function declared(string $body, string $length): Request
    {
        $request = Request::create('/', 'POST', content: $body);
        $request->headers->set('Content-Length', $length);

        return $request;
    }
}
