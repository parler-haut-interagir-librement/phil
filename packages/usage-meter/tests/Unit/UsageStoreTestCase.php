<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit;

use DateTimeImmutable;
use Phil\UsageMeter\Counter;
use Phil\UsageMeter\Increment;
use Phil\UsageMeter\UsageStore;
use PHPUnit\Framework\TestCase;

/**
 * What every store does, run against each of them.
 */
abstract class UsageStoreTestCase extends TestCase
{
    abstract protected function store(): UsageStore;

    public function testAnIncrementCreatesTheCounterAndAddsToIt(): void
    {
        $store = $this->store();

        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 3));
        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 4));
        self::assertSame(7, $store->total('account-1', 'exports', self::at(100), self::at(101)));
    }

    public function testACeilingRefusesWhatWouldGoPastIt(): void
    {
        $store = $this->store();

        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 3, 5));
        self::assertSame(Increment::Refused, $store->add($this->counter(100), 3, 5));
        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 2, 5));
        self::assertSame(Increment::Refused, $store->add($this->counter(100), 1, 5));
        self::assertSame(5, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
    }

    public function testACeilingBelowTheAmountRefusesANewCounter(): void
    {
        $store = $this->store();

        self::assertSame(Increment::Refused, $store->add($this->counter(100), 3, 2));
        self::assertSame(Increment::Refused, $store->add($this->counter(100), 1, -1));
        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 2, 2));
        self::assertSame(2, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
    }

    public function testAKeyIsCountedOnce(): void
    {
        $store = $this->store();

        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 3, null, 'request-1'));
        self::assertSame(Increment::Duplicate, $store->add($this->counter(100), 3, null, 'request-1'));
        self::assertSame(Increment::Duplicate, $store->add($this->counter(200), 3, 1, 'request-1'));
        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 1, null, 'request-2'));
        self::assertSame(4, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
    }

    public function testAKeyBelongsToItsSubjectAndMeter(): void
    {
        $store = $this->store();

        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 1, null, 'request-1'));
        self::assertSame(Increment::Recorded, $store->add(new Counter('account-2', 'exports', self::at(100), self::at(200)), 1, null, 'request-1'));
        self::assertSame(Increment::Recorded, $store->add(new Counter('account-1', 'imports', self::at(100), self::at(200)), 1, null, 'request-1'));
    }

    public function testARefusedIncrementLeavesItsKeyFree(): void
    {
        $store = $this->store();

        self::assertSame(Increment::Refused, $store->add($this->counter(100), 3, 2, 'request-1'));
        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 2, 2, 'request-1'));
        self::assertSame(2, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
    }

    public function testTheTotalSumsTheCountersStartingInTheRange(): void
    {
        $store = $this->store();
        $store->add($this->counter(100), 1);
        $store->add($this->counter(200), 10);
        $store->add($this->counter(300), 100);
        $store->add(new Counter('account-2', 'exports', self::at(200), self::at(400)), 1000);
        $store->add(new Counter('account-1', 'imports', self::at(200), self::at(400)), 10000);

        self::assertSame(11, $store->total('account-1', 'exports', self::at(100), self::at(300)));
        self::assertSame(110, $store->total('account-1', 'exports', self::at(101), self::at(301)));
        self::assertSame(0, $store->total('account-1', 'exports', self::at(200), self::at(200)));
        self::assertSame(0, $store->total('account-3', 'exports', self::at(0), self::at(1000)));
        self::assertSame(1000, $store->total('account-2', 'exports', self::at(0), self::at(1000)));
        self::assertSame(10000, $store->total('account-1', 'imports', self::at(0), self::at(1000)));
    }

    public function testThePurgeForgetsWhatStoppedCounting(): void
    {
        $store = $this->store();
        $store->add(new Counter('account-1', 'exports', self::at(100), self::at(500)), 1, null, 'a');
        $store->add(new Counter('account-1', 'exports', self::at(200), self::at(600)), 2, null, 'b');
        $store->add(new Counter('account-2', 'imports', self::at(200), self::at(700)), 4, null, 'c');

        self::assertSame(0, $store->purge(self::at(499)));
        self::assertSame(4, $store->purge(self::at(600)));
        self::assertSame(4, $store->total('account-2', 'imports', self::at(0), self::at(1000)));
        self::assertSame(0, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 1, null, 'a'));
        self::assertSame(Increment::Duplicate, $store->add(new Counter('account-2', 'imports', self::at(200), self::at(700)), 1, null, 'c'));
        self::assertSame(Increment::Recorded, $store->add(new Counter('account-2', 'imports', self::at(200), self::at(700)), 1, null, 'a'));
        self::assertSame(2, $store->purge(self::at(600)));
    }

    public function testARefundGivesBackWhatItsKeyCounted(): void
    {
        $store = $this->store();
        $store->add($this->counter(100), 3, 5, 'request-1');
        $store->add($this->counter(100), 2, 5, 'request-2');

        self::assertSame(3, $store->refund('account-1', 'exports', 'request-1'));
        self::assertSame(2, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
        self::assertSame(Increment::Recorded, $store->add($this->counter(100), 3, 5));
    }

    public function testARefundIsIdempotentAndBoundedByWhatItsKeyCounted(): void
    {
        $store = $this->store();
        $store->add($this->counter(100), 5, null, 'request-1');
        $store->add($this->counter(100), 10);

        self::assertSame(2, $store->refund('account-1', 'exports', 'request-1', 2));
        self::assertSame(0, $store->refund('account-1', 'exports', 'request-1', 2));
        self::assertSame(0, $store->refund('account-1', 'exports', 'request-1', 1));
        self::assertSame(13, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
        self::assertSame(2, $store->refund('account-1', 'exports', 'request-1', 4));
        self::assertSame(1, $store->refund('account-1', 'exports', 'request-1', 50));
        self::assertSame(0, $store->refund('account-1', 'exports', 'request-1'));
        self::assertSame(10, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
    }

    public function testARefundGoesToTheCounterItsKeyCountedIn(): void
    {
        $store = $this->store();
        $store->add($this->counter(100), 4, null, 'request-1');
        $store->add($this->counter(200), 7);

        self::assertSame(4, $store->refund('account-1', 'exports', 'request-1'));
        self::assertSame(0, $store->total('account-1', 'exports', self::at(100), self::at(101)));
        self::assertSame(7, $store->total('account-1', 'exports', self::at(200), self::at(201)));
    }

    public function testAnUnknownKeyOrAnotherSubjectOrMeterGivesNothingBack(): void
    {
        $store = $this->store();
        $store->add($this->counter(100), 4, null, 'request-1');

        self::assertSame(0, $store->refund('account-1', 'exports', 'request-2'));
        self::assertSame(0, $store->refund('account-2', 'exports', 'request-1'));
        self::assertSame(0, $store->refund('account-1', 'imports', 'request-1'));
        self::assertSame(4, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
    }

    public function testARefusedOrDuplicateIncrementLeavesTheKeyAmountAsFirstCounted(): void
    {
        $store = $this->store();
        $store->add($this->counter(100), 9, 2, 'request-1');
        $store->add($this->counter(100), 2, 2, 'request-1');
        $store->add($this->counter(100), 6, null, 'request-1');

        self::assertSame(2, $store->refund('account-1', 'exports', 'request-1'));
        self::assertSame(0, $store->total('account-1', 'exports', self::at(0), self::at(1000)));
    }

    public function testAPurgedKeyGivesNothingBack(): void
    {
        $store = $this->store();
        $store->add($this->counter(100), 4, null, 'request-1');
        $store->purge(self::at(200));

        self::assertSame(0, $store->refund('account-1', 'exports', 'request-1'));
    }

    protected static function at(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$timestamp);
    }

    private function counter(int $start): Counter
    {
        return new Counter('account-1', 'exports', self::at($start), self::at($start + 100));
    }
}
