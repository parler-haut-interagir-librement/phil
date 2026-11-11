<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Application\Exception\LeaseHeld;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Model\LeasedResource;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\EditLockBundle\Infrastructure\Doctrine\DoctrineLeaseRepository;
use Phil\EditLockBundle\Tests\Unit\Fixture\PausingRepository;
use Phil\EditLockBundle\Tests\Unit\Fixture\Policy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Two writers on two connections to one database: the second claims a key while the first is in the middle of its
 * own claim on the same resource. The second waits for the first, gives up after a short wait, and once the first has
 * committed finds its lease. Each database gets these tests through its own connection.
 */
abstract class ConcurrentClaimsTestCase extends TestCase
{
    /**
     * A new connection to the database of the test, its waits for a lock cut short.
     */
    abstract protected function connect(): Connection;

    protected function setUp(): void
    {
        $connection = $this->connect();
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        $classes = [$entityManager->getClassMetadata(Lease::class), $entityManager->getClassMetadata(LeasedResource::class)];
        $schema = new SchemaTool($entityManager);
        $schema->dropSchema($classes);
        $schema->createSchema($classes);

        $connection->close();
    }

    public function testAPartClaimedWhileTheWholeIsClaimedWaitsThenFindsItHeld(): void
    {
        $this->race(LeaseKey::of('page'), LeaseKey::of('page', 'b1'));
    }

    public function testTheWholeClaimedWhileAPartIsClaimedWaitsThenFindsItHeld(): void
    {
        $this->race(LeaseKey::of('page', 'b1'), LeaseKey::of('page'));
    }

    public function testClaimsOnAResourceClaimedBeforeWaitForEachOtherToo(): void
    {
        $earlier = $this->locks($this->connect());
        $earlier->acquire(LeaseKey::of('page', 'b9'), Holder::of('ada', 'Ada'));
        self::assertTrue($earlier->release(LeaseKey::of('page', 'b9'), Holder::of('ada', 'Ada')));

        $this->race(LeaseKey::of('page', 'b2'), LeaseKey::of('page'));
    }

    public function testClaimsOnTwoResourcesClaimedBeforeWaitForEachOtherOnlyWhereTheWholeDatabaseIsLocked(): void
    {
        $bob = $this->locks($this->connect());
        $bob->acquire(LeaseKey::of('page', 'b9'), Holder::of('bob', 'Bob'));
        $bob->acquire(LeaseKey::of('other', 'b9'), Holder::of('bob', 'Bob'));
        $bob->leave('page', Holder::of('bob', 'Bob'));
        $bob->leave('other', Holder::of('bob', 'Bob'));

        $meanwhile = null;
        $ada = $this->locks($this->connect(), static function () use ($bob, &$meanwhile): void {
            try {
                $meanwhile = $bob->acquire(LeaseKey::of('other'), Holder::of('bob', 'Bob'));
            } catch (LeaseHeld $leaseHeld) {
                $meanwhile = $leaseHeld;
            }
        });

        $ada->acquire(LeaseKey::of('page'), Holder::of('ada', 'Ada'));

        self::assertInstanceOf($this->locksTheWholeDatabase() ? LeaseHeld::class : Lease::class, $meanwhile);
        self::assertSame('page', $ada->presence('page')[0]->key()->toString());
    }

    /**
     * Whether a writer locks the whole database rather than rows, as SQLite does.
     */
    protected function locksTheWholeDatabase(): bool
    {
        return false;
    }

    private function race(LeaseKey $first, LeaseKey $second): void
    {
        $bob = $this->locks($this->connect());
        $meanwhile = null;
        $ada = $this->locks($this->connect(), static function () use ($bob, $second, &$meanwhile): void {
            try {
                $meanwhile = $bob->acquire($second, Holder::of('bob', 'Bob'));
            } catch (LeaseHeld $leaseHeld) {
                $meanwhile = $leaseHeld;
            }
        });

        $ada->acquire($first, Holder::of('ada', 'Ada'));

        self::assertInstanceOf(LeaseHeld::class, $meanwhile, 'the second claim was granted during the first');
        self::assertNull($meanwhile->lease, 'the second claim gave up waiting');
        self::assertSame([$first->toString()], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $bob->presence('page')));

        try {
            $bob->acquire($second, Holder::of('bob', 'Bob'));
            self::fail('Two holders share the whole and a part.');
        } catch (LeaseHeld $leaseHeld) {
            self::assertSame(['ada', $first->toString()], [$leaseHeld->lease?->holder()->id, $leaseHeld->lease?->key()->toString()]);
        }
    }

    /**
     * @param (Closure(): void)|null $meanwhile what runs in the middle of the first claim
     */
    private function locks(Connection $connection, ?Closure $meanwhile = null): EditLocks
    {
        $store = new DoctrineLeaseRepository($connection);

        return new EditLocks($meanwhile instanceof Closure ? new PausingRepository($store, $meanwhile) : $store, new MockClock('2026-11-06 10:00:00 UTC'), 30, new Policy());
    }
}
