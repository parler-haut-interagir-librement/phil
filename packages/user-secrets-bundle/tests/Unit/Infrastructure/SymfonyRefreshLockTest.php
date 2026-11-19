<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Infrastructure;

use Closure;
use Override;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\KeepingLockFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(SymfonyRefreshLock::class)]
final class SymfonyRefreshLockTest extends TestCase
{
    public function testTheLockIsHeldWhileTheRefreshRunsAndReleasedAfter(): void
    {
        $store = new InMemoryStore();
        $factory = new LockFactory($store);
        $lock = new SymfonyRefreshLock($factory, 5.0);
        $key = SymfonyRefreshLock::key(Owner::fromString('member-1'), SecretName::fromString('github'));

        $result = $lock->synchronized(Owner::fromString('member-1'), SecretName::fromString('github'), static function () use ($factory, $key): string {
            self::assertFalse($factory->createLock($key)->acquire(), 'another process waits');
            self::assertTrue($factory->createLock(SymfonyRefreshLock::key(Owner::fromString('member-1'), SecretName::fromString('gitlab')))->acquire(), 'another secret does not');

            return 'refreshed';
        });

        self::assertSame('refreshed', $result);
        self::assertTrue($factory->createLock($key)->acquire());
    }

    public function testTheLockIsReleasedWhenTheRefreshFails(): void
    {
        $factory = new LockFactory(new InMemoryStore());

        $failure = new RuntimeException('The service is down.');
        $caught = null;

        try {
            new SymfonyRefreshLock($factory)->synchronized(Owner::fromString('member-1'), SecretName::fromString('github'), $this->failing($failure));
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($failure, $caught);
        self::assertTrue($factory->createLock(SymfonyRefreshLock::key(Owner::fromString('member-1'), SecretName::fromString('github')))->acquire());
    }

    public function testTheLockIsReleasedByTheLockItselfNotByItsDestruction(): void
    {
        $factory = new KeepingLockFactory(new InMemoryStore());

        new SymfonyRefreshLock($factory, 5.0)->synchronized(Owner::fromString('member-1'), SecretName::fromString('github'), static fn (): null => null);

        self::assertFalse($factory->last()->isAcquired(), 'released after the refresh');
    }

    public function testTheLockIsReleasedByTheLockItselfWhenTheRefreshFails(): void
    {
        $factory = new KeepingLockFactory(new InMemoryStore());

        try {
            new SymfonyRefreshLock($factory, 5.0)->synchronized(Owner::fromString('member-1'), SecretName::fromString('github'), $this->failing(new RuntimeException('The service is down.')));
            self::fail('The failure goes through.');
        } catch (RuntimeException) {
            self::assertFalse($factory->last()->isAcquired(), 'released after a failure');
        }
    }

    public function testAProcessWaitsForTheLockAnotherHoldsAndRunsOnlyOnceItHasIt(): void
    {
        // Two other processes hold the lock when this one asks, and let it go one after the other.
        $store = new class implements PersistingStoreInterface {
            public int $conflicts = 2;

            public int $attempts = 0;

            private readonly InMemoryStore $store;

            public function __construct()
            {
                $this->store = new InMemoryStore();
            }

            #[Override]
            public function save(Key $key): void
            {
                ++$this->attempts;

                if ($this->conflicts > 0) {
                    --$this->conflicts;

                    throw new LockConflictedException();
                }

                $this->store->save($key);
            }

            #[Override]
            public function delete(Key $key): void
            {
                $this->store->delete($key);
            }

            #[Override]
            public function exists(Key $key): bool
            {
                return $this->store->exists($key);
            }

            #[Override]
            public function putOffExpiration(Key $key, float $ttl): void
            {
                $this->store->putOffExpiration($key, $ttl);
            }
        };
        $factory = new KeepingLockFactory($store);

        $held = new SymfonyRefreshLock($factory, 5.0)->synchronized(Owner::fromString('member-1'), SecretName::fromString('github'), static fn (): bool => $factory->last()->isAcquired());

        self::assertTrue($held, 'the lock was taken before the refresh ran');
        self::assertSame(3, $store->attempts);
    }

    public function testTheLockLastsItsTimeToLive(): void
    {
        $store = new class implements PersistingStoreInterface {
            /** @var list<float> */
            public array $ttls = [];

            private readonly InMemoryStore $store;

            public function __construct()
            {
                $this->store = new InMemoryStore();
            }

            #[Override]
            public function save(Key $key): void
            {
                $this->store->save($key);
            }

            #[Override]
            public function delete(Key $key): void
            {
                $this->store->delete($key);
            }

            #[Override]
            public function exists(Key $key): bool
            {
                return $this->store->exists($key);
            }

            #[Override]
            public function putOffExpiration(Key $key, float $ttl): void
            {
                $this->ttls[] = $ttl;
                $this->store->putOffExpiration($key, $ttl);
            }
        };

        new SymfonyRefreshLock(new LockFactory($store), 7.0)->synchronized(Owner::fromString('member-1'), SecretName::fromString('github'), static fn (): null => null);
        new SymfonyRefreshLock(new LockFactory($store))->synchronized(Owner::fromString('member-1'), SecretName::fromString('github'), static fn (): null => null);

        self::assertSame([7.0, 30.0], $store->ttls);
    }

    public function testTheKeyIsAHashOfTheOwnerAndTheName(): void
    {
        self::assertSame(
            SymfonyRefreshLock::PREFIX.hash('sha256', '["member-1","github"]'),
            SymfonyRefreshLock::key(Owner::fromString('member-1'), SecretName::fromString('github')),
        );
        self::assertSame('phil_user_secrets.refresh.', SymfonyRefreshLock::PREFIX);
    }

    /**
     * @return Closure(): string
     */
    private function failing(RuntimeException $failure): Closure
    {
        return static function () use ($failure): string {
            throw $failure;
        };
    }
}
