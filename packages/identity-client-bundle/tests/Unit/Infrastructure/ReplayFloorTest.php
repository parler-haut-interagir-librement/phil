<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\IdentityClientBundle\Infrastructure\Store\CacheReplayFloor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

#[CoversClass(CacheReplayFloor::class)]
final class ReplayFloorTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private ArrayAdapter $pool;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ArrayAdapter();
        $this->clock = new MockClock(new DateTimeImmutable('@'.self::NOW));
    }

    public function testAColdPoolRecordsNow(): void
    {
        self::assertSame(self::NOW, $this->floor()->floor());

        $item = $this->pool->getItem(CacheReplayFloor::KEY);
        self::assertTrue($item->isHit());
        self::assertSame(self::NOW, $item->get());
    }

    public function testARecordedFloorStays(): void
    {
        $this->floor()->floor();
        $this->clock->modify('+1 hour');

        self::assertSame(self::NOW, $this->floor()->floor());
    }

    public function testAFlushedPoolRaisesTheFloor(): void
    {
        $this->floor()->floor();
        $this->clock->modify('+1 hour');
        $this->pool->clear();

        self::assertSame(self::NOW + 3600, $this->floor()->floor());
    }

    public function testAnEntryThatIsNotAnInstantIsRecordedAgain(): void
    {
        $this->pool->save($this->pool->getItem(CacheReplayFloor::KEY)->set('yesterday'));

        self::assertSame(self::NOW, $this->floor()->floor());
        self::assertSame(self::NOW, $this->pool->getItem(CacheReplayFloor::KEY)->get());
    }

    public function testAConfiguredFloorAboveTheRecordedOneRaisesIt(): void
    {
        $this->record(self::NOW - 100);

        self::assertSame(self::NOW - 10, $this->floor(self::NOW - 10)->floor());
    }

    public function testAConfiguredFloorReachedThisSecondIsInForce(): void
    {
        $this->record(self::NOW - 100);

        self::assertSame(self::NOW, $this->floor(self::NOW)->floor());
    }

    public function testAConfiguredFloorInTheFutureIsNotInForceYet(): void
    {
        $this->record(self::NOW - 100);

        self::assertSame(self::NOW - 100, $this->floor(self::NOW + 1)->floor());
    }

    public function testAConfiguredFloorBelowTheRecordedOneChangesNothing(): void
    {
        $this->record(self::NOW - 100);

        self::assertSame(self::NOW - 100, $this->floor(self::NOW - 200)->floor());
    }

    private function record(int $floor): void
    {
        $this->pool->save($this->pool->getItem(CacheReplayFloor::KEY)->set($floor));
    }

    private function floor(?int $notBefore = null): CacheReplayFloor
    {
        return new CacheReplayFloor($this->pool, $this->clock, $notBefore);
    }
}
