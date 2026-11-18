<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Lock;

use Override;
use Phil\PrivacyBundle\Application\Exception\LockLost;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ExpiringLockStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

#[CoversClass(HeldLock::class)]
#[CoversClass(LockLost::class)]
final class HeldLockTest extends TestCase
{
    private MockClock $clock;

    private ExpiringLockStore $store;

    private LockFactory $locks;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17T00:00:00Z');
        $this->store = new ExpiringLockStore($this->clock);
        $this->locks = new LockFactory($this->store);
    }

    public function testRenewingPutsTheExpiryOffByTheLifetimeOfTheLock(): void
    {
        $held = new HeldLock($lock = $this->acquired());

        $this->clock->sleep(50);
        $held->renew();
        $this->clock->sleep(50);

        self::assertTrue($lock->isAcquired(), 'Renewed at 50 s, the lease of 60 s lasts until 110 s.');
        self::assertFalse($this->locks->createLock('work', 60.0, false)->acquire());
    }

    public function testAWorkLongerThanTheLifetimeKeepsTheLockWhenRenewedBetweenItsSteps(): void
    {
        $held = new HeldLock($lock = $this->acquired());

        for ($step = 0; $step < 10; ++$step) {
            $this->clock->sleep(59);
            $held->renew();
        }

        self::assertTrue($lock->isAcquired());
    }

    public function testALeaseTakenByAnotherProcessIsLost(): void
    {
        $held = new HeldLock($this->acquired());
        $this->clock->sleep(61);
        self::assertTrue($this->locks->createLock('work', 60.0, false)->acquire());

        $this->expectExceptionObject(LockLost::create());

        $held->renew();
    }

    public function testAnExpiredLeaseIsLostEvenWhenNobodyTookIt(): void
    {
        $held = new HeldLock($this->acquired());
        $this->clock->sleep(61);

        try {
            $held->renew();
            self::fail('An expired lease cannot be renewed.');
        } catch (LockLost $lockLost) {
            self::assertNotNull($lockLost->getPrevious(), 'The store refused to put the expiry off.');
        }
    }

    public function testALockTheStoreCannotConfirmIsLost(): void
    {
        $held = new HeldLock($this->acquired());
        $this->store->failing = true;

        $this->expectException(LockLost::class);

        $held->renew();
    }

    public function testALockTheStoreNoLongerHasIsLostWithoutACause(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('refresh');
        $lock->method('isAcquired')->willReturn(false);
        $lock->expects(self::never())->method('release');
        $held = new HeldLock($lock);

        try {
            $held->renew();
            self::fail('A lock the store no longer has is lost.');
        } catch (LockLost $lockLost) {
            self::assertNull($lockLost->getPrevious());
        }

        $held->release();
    }

    public function testALockTheStoreFailedToConfirmStaysLostAndIsNeitherRenewedNorReleased(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('refresh')->willThrowException(new RuntimeException('The store is out of reach.'));
        $lock->expects(self::never())->method('release');
        $held = new HeldLock($lock);

        try {
            $held->renew();
            self::fail('A lock the store cannot confirm is lost.');
        } catch (LockLost) {
        }

        $this->expectException(LockLost::class);

        try {
            $held->renew();
        } finally {
            $held->release();
        }
    }

    public function testALostLockStaysLostAndIsNeitherRenewedNorReleased(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('refresh');
        $lock->method('isAcquired')->willReturn(false);
        $lock->expects(self::never())->method('release');
        $held = new HeldLock($lock);

        try {
            $held->renew();
        } catch (LockLost) {
        }

        $this->expectException(LockLost::class);

        try {
            $held->renew();
        } finally {
            $held->release();
        }
    }

    public function testTheLockIsReleasedOnce(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('release');
        $held = new HeldLock($lock);

        $held->release();
        $held->release();
    }

    public function testAReleaseTheStoreRefusesIsLeftToTheExpiry(): void
    {
        $held = new HeldLock($this->acquired());
        $this->store->failing = true;

        $held->release();

        $this->store->failing = false;
        $this->clock->sleep(61);
        self::assertTrue($this->locks->createLock('work', 60.0, false)->acquire());
    }

    public function testTheMessageNamesNeitherTheLockNorThePerson(): void
    {
        self::assertSame('The lock of this work was lost before its next step; the work stopped.', LockLost::create()->getMessage());
    }

    private function acquired(): LockInterface
    {
        $lock = $this->locks->createLock('work', 60.0, false);
        self::assertTrue($lock->acquire());

        return $lock;
    }
}
