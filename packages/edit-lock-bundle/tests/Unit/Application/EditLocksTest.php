<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Application;

use Phil\EditLockBundle\Application\DefaultEditPolicy;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Application\Exception\EditRefused;
use Phil\EditLockBundle\Application\Exception\LeaseHeld;
use Phil\EditLockBundle\Application\Exception\LeaseLost;
use Phil\EditLockBundle\Application\NullBroadcaster;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\EditLockBundle\Infrastructure\Memory\InMemoryLeaseRepository;
use Phil\EditLockBundle\Tests\Unit\Fixture\Policy;
use Phil\EditLockBundle\Tests\Unit\Fixture\RacingRepository;
use Phil\EditLockBundle\Tests\Unit\Fixture\RecordingBroadcaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(EditLocks::class)]
#[CoversClass(DefaultEditPolicy::class)]
#[CoversClass(NullBroadcaster::class)]
#[CoversClass(LeaseHeld::class)]
#[CoversClass(LeaseLost::class)]
#[CoversClass(EditRefused::class)]
final class EditLocksTest extends TestCase
{
    private MockClock $clock;

    private InMemoryLeaseRepository $store;

    private RecordingBroadcaster $told;

    private EditLocks $locks;

    private Holder $ada;

    private Holder $bob;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-06 10:00:00 UTC');
        $this->store = new InMemoryLeaseRepository();
        $this->told = new RecordingBroadcaster();
        $this->locks = new EditLocks($this->store, $this->clock, 30, new Policy(), $this->told);
        $this->ada = Holder::of('ada', 'Ada');
        $this->bob = Holder::of('bob', 'Bob');
    }

    public function testABlockIsAcquiredKeptAndReleased(): void
    {
        $key = LeaseKey::of('page', 'b1');
        $lease = $this->locks->acquire($key, $this->bob);

        self::assertSame(['page#b1', 'bob', '10:00:30', 1], [$lease->key()->toString(), $lease->holder()->id, $lease->expiresAt()->format('H:i:s'), $lease->version]);
        self::assertSame(30, $this->locks->seconds());
        self::assertSame($lease, $this->store->get($key));

        $this->clock->sleep(20);
        $again = $this->locks->acquire($key, Holder::of('bob', 'Bobby'));
        self::assertSame(['bob', 'Bobby', '10:00:00', '10:00:50', 2], [$again->holder()->id, $again->holder()->name, $again->acquiredAt()->format('H:i:s'), $again->expiresAt()->format('H:i:s'), $again->version]);

        $this->clock->sleep(25);
        $beat = $this->locks->heartbeat($key, $this->bob);
        self::assertSame(['10:01:15', 3], [$beat->expiresAt()->format('H:i:s'), $beat->version]);
        self::assertSame($beat, $this->store->get($key));

        self::assertTrue($this->locks->release($key, $this->bob));
        self::assertFalse($this->locks->release($key, $this->bob));
        self::assertNull($this->store->get($key));
        self::assertSame([
            'presence page [page#b1=bob] 10:00:00',
            'presence page [page#b1=bob] 10:00:20',
            'presence page [page#b1=bob] 10:00:45',
            'presence page [] 10:00:45',
        ], $this->told->told);
    }

    public function testAnotherHolderWaitsForTheLease(): void
    {
        $key = LeaseKey::of('page', 'b1');
        $this->locks->acquire($key, $this->bob);
        $this->clock->sleep(29);

        try {
            $this->locks->acquire($key, Holder::of('cy', 'Cy'));
            self::fail('Two holders of one block.');
        } catch (LeaseHeld $leaseHeld) {
            self::assertSame(['page#b1', 'bob'], [$leaseHeld->lease?->key()->toString(), $leaseHeld->lease?->holder()->id]);
            self::assertSame('"page#b1" is held by "Bob".', $leaseHeld->getMessage());
        }

        $this->clock->sleep(1);
        $passed = $this->locks->acquire($key, Holder::of('cy', 'Cy'));
        self::assertSame(['cy', '10:00:30', '10:01:00', 2], [$passed->holder()->id, $passed->acquiredAt()->format('H:i:s'), $passed->expiresAt()->format('H:i:s'), $passed->version]);

        try {
            $this->locks->heartbeat($key, $this->bob);
            self::fail('A lost lease was kept.');
        } catch (LeaseLost $leaseLost) {
            self::assertSame('"page#b1" is no longer held.', $leaseLost->getMessage());
        }

        self::assertFalse($this->locks->release($key, $this->bob));
        self::assertSame('cy', $this->store->get($key)?->holder()->id);
    }

    public function testTheWholeResourceAndItsPartsExcludeEachOther(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);
        $this->locks->acquire(LeaseKey::of('page', 'b2'), Holder::of('cy', 'Cy'));
        $this->locks->acquire(LeaseKey::of('page', 'b3'), $this->bob);
        $this->locks->acquire(LeaseKey::of('other'), Holder::of('cy', 'Cy'));

        try {
            $this->locks->acquire(LeaseKey::of('page'), $this->bob);
            self::fail('The whole of a page another holder edits.');
        } catch (LeaseHeld $leaseHeld) {
            self::assertSame('page#b2', $leaseHeld->lease?->key()->toString());
        }

        $this->locks->release(LeaseKey::of('page', 'b2'), Holder::of('cy', 'Cy'));
        $whole = $this->locks->acquire(LeaseKey::of('page'), $this->bob);
        self::assertSame('page', $whole->key()->toString());

        try {
            $this->locks->acquire(LeaseKey::of('page', 'b9'), Holder::of('cy', 'Cy'));
            self::fail('A block of a page another holder holds whole.');
        } catch (LeaseHeld $leaseHeld) {
            self::assertSame('page', $leaseHeld->lease?->key()->toString());
        }

        self::assertSame(['page', 'page#b1', 'page#b3'], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $this->locks->presence('page')));
        self::assertTrue($this->locks->holds(LeaseKey::of('page', 'b9'), $this->bob));
        self::assertTrue($this->locks->holds(LeaseKey::of('page', 'b1'), $this->bob));
        self::assertFalse($this->locks->holds(LeaseKey::of('page', 'b9'), Holder::of('cy', 'Cy')));
        self::assertTrue($this->locks->holds(LeaseKey::of('other', 'x'), Holder::of('cy', 'Cy')));
        self::assertFalse($this->locks->holds(LeaseKey::of('other', 'x'), $this->bob));
        self::assertFalse($this->locks->holds(LeaseKey::of('elsewhere'), $this->bob));

        $this->clock->sleep(30);
        self::assertFalse($this->locks->holds(LeaseKey::of('page', 'b1'), $this->bob));
        self::assertSame([], $this->locks->presence('page'));
    }

    public function testAHeartbeatNeedsTheLease(): void
    {
        foreach ([null, 'cy'] as $holder) {
            if (null !== $holder) {
                $this->locks->acquire(LeaseKey::of('page', 'b1'), Holder::of($holder, 'Cy'));
            }

            try {
                $this->locks->heartbeat(LeaseKey::of('page', 'b1'), $this->bob);
                self::fail('A heartbeat without the lease.');
            } catch (LeaseLost) {
            }
        }

        $this->locks->acquire(LeaseKey::of('draft', 'b1'), $this->bob);
        $this->clock->sleep(40);
        self::assertSame('10:01:10', $this->locks->heartbeat(LeaseKey::of('draft', 'b1'), $this->bob)->expiresAt()->format('H:i:s'));

        $this->clock->sleep(40);
        $this->locks->acquire(LeaseKey::of('draft'), Holder::of('cy', 'Cy'));

        try {
            $this->locks->heartbeat(LeaseKey::of('draft', 'b1'), $this->bob);
            self::fail('A block of a page another holder took whole.');
        } catch (LeaseLost) {
        }
    }

    public function testAHeartbeatOfAHolderNoLongerAllowedToEditLosesTheLease(): void
    {
        $key = LeaseKey::of('page', 'b1');
        $this->locks->acquire($key, $this->bob);
        $this->told->told = [];
        $revoked = new EditLocks($this->store, $this->clock, 30, new Policy(['ada', 'cy']), $this->told);

        try {
            $revoked->heartbeat($key, $this->bob);
            self::fail('A heartbeat of a holder the policy refuses.');
        } catch (EditRefused $editRefused) {
            self::assertSame('Editing "page" is not allowed.', $editRefused->getMessage());
        }

        self::assertNull($this->store->get($key));
        self::assertSame(['presence page [] 10:00:00'], $this->told->told);

        $this->told->told = [];

        try {
            $revoked->heartbeat($key, $this->bob);
            self::fail('A heartbeat of a holder the policy refuses.');
        } catch (EditRefused) {
        }

        self::assertSame([], $this->told->told);
    }

    public function testAnOwnerTakesOver(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);
        $this->locks->acquire(LeaseKey::of('page', 'b2'), Holder::of('cy', 'Cy'));
        $this->locks->acquire(LeaseKey::of('page', 'b3'), $this->ada);

        $this->told->told = [];

        try {
            $this->locks->takeOver(LeaseKey::of('page', 'b1'), Holder::of('cy', 'Cy'));
            self::fail('A writer took over.');
        } catch (EditRefused $editRefused) {
            self::assertSame('Taking over "page#b1" is not allowed.', $editRefused->getMessage());
        }

        $this->clock->sleep(5);
        $taken = $this->locks->takeOver(LeaseKey::of('page', 'b1'), $this->ada);
        self::assertSame(['ada', '10:00:05', 2], [$taken->holder()->id, $taken->acquiredAt()->format('H:i:s'), $taken->version]);
        self::assertSame('page#b2', $this->store->get(LeaseKey::of('page', 'b2'))?->key()->toString());

        $whole = $this->locks->takeOver(LeaseKey::of('page'), $this->ada);
        self::assertSame(['page', 'ada', 1], [$whole->key()->toString(), $whole->holder()->id, $whole->version]);
        self::assertNull($this->store->get(LeaseKey::of('page', 'b2')));
        self::assertSame(['page', 'page#b1', 'page#b3'], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $this->locks->presence('page')));
        self::assertSame(2, $this->locks->takeOver(LeaseKey::of('page'), $this->ada)->version);
        self::assertSame([
            'presence page [page#b1=ada, page#b2=cy, page#b3=ada] 10:00:05',
            'presence page [page=ada, page#b1=ada, page#b3=ada] 10:00:05',
            'presence page [page=ada, page#b1=ada, page#b3=ada] 10:00:05',
        ], $this->told->told);
    }

    public function testThePolicyDecidesWhoEdits(): void
    {
        foreach ([[LeaseKey::of('page'), Holder::of('eve', 'Eve')], [LeaseKey::of('secret', 'b1'), $this->ada]] as [$key, $holder]) {
            foreach ([$this->locks->acquire(...), $this->locks->takeOver(...)] as $take) {
                try {
                    $take($key, $holder);
                    self::fail('A writer the policy refuses took a lease.');
                } catch (EditRefused $editRefused) {
                    self::assertSame(\sprintf('Editing "%s" is not allowed.', $key->resource), $editRefused->getMessage());
                }
            }
        }

        self::assertSame([true, false, false], [$this->locks->mayEdit($this->ada, 'page'), $this->locks->mayEdit($this->ada, 'secret'), $this->locks->mayEdit(Holder::of('eve', 'Eve'), 'page')]);
        self::assertSame([], $this->told->told);
    }

    public function testAWriterLeavesAPage(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);
        $this->locks->acquire(LeaseKey::of('page', 'b2'), Holder::of('cy', 'Cy'));
        $this->locks->acquire(LeaseKey::of('page', 'b3'), $this->bob);
        $this->locks->acquire(LeaseKey::of('other', 'b1'), $this->bob);

        $this->told->told = [];

        self::assertSame(2, $this->locks->leave('page', $this->bob));
        self::assertSame(['presence page [page#b2=cy] 10:00:00'], $this->told->told, 'told once leases are released');
        self::assertSame(0, $this->locks->leave('page', $this->bob));
        self::assertSame(['page#b2'], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $this->store->ofResource('page')));
        self::assertNotNull($this->store->get(LeaseKey::of('other', 'b1')));
        self::assertSame(['presence page [page#b2=cy] 10:00:00'], $this->told->told);
    }

    public function testOnlyActiveLeasesArePresent(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'a1'), $this->bob);
        $this->clock->sleep(30);
        $this->told->told = [];
        $this->locks->acquire(LeaseKey::of('page', 'b1'), Holder::of('cy', 'Cy'));

        self::assertSame(['page#b1'], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $this->locks->presence('page')));
        self::assertSame(['presence page [page#b1=cy] 10:00:30'], $this->told->told);
    }

    public function testExpiredLeasesArePurged(): void
    {
        $this->locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);
        $this->clock->sleep(10);
        $this->locks->acquire(LeaseKey::of('page', 'b2'), $this->bob);
        $this->clock->sleep(20);

        self::assertSame(1, $this->locks->purge());
        self::assertSame(0, $this->locks->purge());
        self::assertSame(['page#b2'], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $this->store->ofResource('page')));
    }

    public function testARaceIsTriedAgainThenGivenUp(): void
    {
        $store = new RacingRepository(2);
        $locks = new EditLocks($store, $this->clock, 30, new Policy(), $this->told);

        self::assertSame('bob', $locks->acquire(LeaseKey::of('page', 'b1'), $this->bob)->holder()->id);
        self::assertSame(3, $store->writes);

        $store->losses = 3;
        $store->writes = 0;

        try {
            $locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);
            self::fail('A race was won.');
        } catch (LeaseHeld $leaseHeld) {
            self::assertNull($leaseHeld->lease);
            self::assertSame('"page#b1" kept changing hands; try again.', $leaseHeld->getMessage());
            self::assertSame(3, $store->writes);
        }

        $store->losses = 1;

        try {
            $locks->heartbeat(LeaseKey::of('page', 'b1'), $this->bob);
            self::fail('A heartbeat lost a race and kept the lease.');
        } catch (LeaseLost) {
        }

        self::assertSame(1, $store->inner->get(LeaseKey::of('page', 'b1'))?->version);
    }

    public function testAClaimTheStoreGaveUpIsTriedAgain(): void
    {
        $store = new RacingRepository(0, timeouts: 2);
        $locks = new EditLocks($store, $this->clock, 30, new Policy(), $this->told);

        self::assertSame('bob', $locks->acquire(LeaseKey::of('page'), $this->bob)->holder()->id);
        self::assertSame([3, 1], [$store->claims, $store->writes], 'two claims given up, the third kept');

        $store->timeouts = 3;
        $store->claims = 0;

        try {
            $locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);
            self::fail('A claim the store gave up was kept.');
        } catch (LeaseHeld $leaseHeld) {
            self::assertNull($leaseHeld->lease);
            self::assertSame(3, $store->claims);
        }

        $store->timeouts = 1;

        try {
            $locks->heartbeat(LeaseKey::of('page'), $this->bob);
            self::fail('A heartbeat the store gave up kept the lease.');
        } catch (LeaseLost) {
        }

        self::assertSame(1, $store->inner->get(LeaseKey::of('page'))?->version);
        self::assertSame(2, $locks->heartbeat(LeaseKey::of('page'), $this->bob)->version);
    }

    public function testTheDefaults(): void
    {
        $locks = new EditLocks($this->store, $this->clock);
        $lease = $locks->acquire(LeaseKey::of('page'), $this->bob);

        self::assertSame([30, '10:00:30'], [$locks->seconds(), $lease->expiresAt()->format('H:i:s')]);
        self::assertTrue($locks->release(LeaseKey::of('page'), $this->bob));
        self::assertSame(1, new EditLocks($this->store, $this->clock, 1)->seconds());

        $locks->acquire(LeaseKey::of('page', 'b1'), $this->bob);

        try {
            $locks->takeOver(LeaseKey::of('page', 'b1'), $this->ada);
            self::fail('The default policy let someone take over.');
        } catch (EditRefused) {
        }

        try {
            new EditLocks($this->store, $this->clock, 0);
            self::fail('A lease of no time.');
        } catch (InvalidLease $invalidLease) {
            self::assertSame('A lease lasts at least one second.', $invalidLease->getMessage());
        }

        new NullBroadcaster()->presence('page', [], $this->clock->now());
        self::assertTrue(new DefaultEditPolicy()->mayEdit($this->ada, 'anything'));
    }
}
