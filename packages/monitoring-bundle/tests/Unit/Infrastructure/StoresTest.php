<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\MonitoringBundle\Infrastructure\Store\CacheHeartbeatStore;
use Phil\MonitoringBundle\Infrastructure\Store\CacheMaintenanceStore;
use Phil\MonitoringBundle\Infrastructure\Store\FileMaintenanceStore;
use Phil\MonitoringBundle\Infrastructure\Store\MaintenanceRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(CacheHeartbeatStore::class)]
#[CoversClass(CacheMaintenanceStore::class)]
#[CoversClass(FileMaintenanceStore::class)]
#[CoversClass(MaintenanceRecord::class)]
final class StoresTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-monitoring-'.bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testTheHeartbeatsOfACachePool(): void
    {
        $pool = new ArrayAdapter(deepClone: false);
        $store = new CacheHeartbeatStore($pool);
        $at = new DateTimeImmutable('2026-11-08 10:00:00.123456 UTC');

        self::assertNull($store->lastBeat('backup'));
        self::assertFalse($store->isAlerted('backup'));

        $store->beat('backup', $at);
        self::assertEquals($at, $store->lastBeat('backup'));
        self::assertFalse($store->isAlerted('backup'));
        self::assertSame(['phil_monitoring.heartbeat.'.hash('xxh128', 'backup') => ['beat' => '2026-11-08T10:00:00.123456+00:00', 'alerted' => false]], $pool->getValues());

        $store->markAlerted('backup');
        self::assertTrue($store->isAlerted('backup'));
        self::assertEquals($at, $store->lastBeat('backup'));
        self::assertNull($store->lastBeat('report'));

        $store->beat('backup', $at);
        self::assertFalse($store->isAlerted('backup'));
    }

    public function testAHeartbeatInAnotherShapeIsNoBeat(): void
    {
        $pool = new ArrayAdapter(deepClone: false);
        $store = new CacheHeartbeatStore($pool);
        $key = 'phil_monitoring.heartbeat.'.hash('xxh128', 'backup');

        foreach (['yesterday', ['beat' => 'yesterday', 'alerted' => 1], ['beat' => 3]] as $value) {
            $pool->save($pool->getItem($key)->set($value));
            self::assertNull($store->lastBeat('backup'));
            self::assertFalse($store->isAlerted('backup'));
        }

        $store->markAlerted('report');
        self::assertTrue($store->isAlerted('report'));
        self::assertNull($store->lastBeat('report'));
    }

    public function testTheMaintenanceInACachePool(): void
    {
        $pool = new ArrayAdapter(deepClone: false);
        $store = new CacheMaintenanceStore($pool, new MockClock('2026-11-08 12:00:00 UTC'), 300);
        $state = new MaintenanceState(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), 60);

        self::assertNull($store->current());

        $store->enable($state);
        self::assertEquals($state, $store->current());
        self::assertSame(['phil_monitoring.maintenance' => ['since' => '2026-11-08T10:00:00+00:00', 'retry_after' => 60]], $pool->getValues());

        $pool->save($pool->getItem('phil_monitoring.maintenance')->set('on'));
        self::assertEquals(new MaintenanceState(new DateTimeImmutable('2026-11-08 12:00:00 UTC'), 300), $store->current());

        $store->disable();
        self::assertNull($store->current());
    }

    public function testTheMaintenanceInAFlagFile(): void
    {
        $path = $this->directory.'/var/maintenance.json';
        $store = new FileMaintenanceStore($path, 300);
        $state = new MaintenanceState(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), 60);

        self::assertNull($store->current());

        $store->enable($state);
        self::assertSame('{"since":"2026-11-08T10:00:00+00:00","retry_after":60}', file_get_contents($path));
        self::assertEquals($state, $store->current());

        $store->disable();
        self::assertFileDoesNotExist($path);
        self::assertNull($store->current());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadable(): iterable
    {
        yield 'empty' => [''];
        yield 'not JSON' => ['on'];
        yield 'too deep' => ['{"since":{"a":{"b":1}}}'];
        yield 'another shape' => ['{"since":"yesterday","retry_after":60}'];
        yield 'a record with a nested extra' => ['{"since":"2026-11-08T10:00:00+00:00","retry_after":60,"extra":{"a":1}}'];
    }

    #[DataProvider('unreadable')]
    public function testAnUnreadableFlagFileIsMaintenanceSinceItsLastChange(string $content): void
    {
        $path = $this->directory.'/maintenance.json';
        new Filesystem()->dumpFile($path, $content);
        touch($path, 1_762_596_000);

        self::assertEquals(new MaintenanceState(new DateTimeImmutable('@1762596000'), 300), new FileMaintenanceStore($path, 300)->current());
    }

    public function testAFlagFileThatCannotBeReadIsMaintenanceSinceItsLastChange(): void
    {
        $path = $this->directory.'/maintenance.json';
        new Filesystem()->dumpFile($path, '{"since":"2026-11-08T10:00:00+00:00","retry_after":60}');
        touch($path, 1_762_596_000);
        chmod($path, 0o000);
        clearstatcache();

        if (is_readable($path)) {
            chmod($path, 0o600);
            self::markTestSkipped('The permissions do not bind this user, root in a container.');
        }

        try {
            $state = new FileMaintenanceStore($path, 300)->current();
        } finally {
            chmod($path, 0o600);
        }

        self::assertEquals(new MaintenanceState(new DateTimeImmutable('@1762596000'), 300), $state);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function otherShapes(): iterable
    {
        yield 'a string' => ['on'];
        yield 'no since' => [['retry_after' => 60]];
        yield 'a since of another type' => [['since' => 1, 'retry_after' => 60]];
        yield 'a since of another format' => [['since' => '2026-11-08 10:00', 'retry_after' => 60]];
        yield 'no delay' => [['since' => '2026-11-08T10:00:00+00:00']];
        yield 'a delay of another type' => [['since' => '2026-11-08T10:00:00+00:00', 'retry_after' => '60']];
        yield 'a negative delay' => [['since' => '2026-11-08T10:00:00+00:00', 'retry_after' => -1]];
    }

    #[DataProvider('otherShapes')]
    public function testARecordInAnotherShapeIsTheFallback(mixed $record): void
    {
        $fallback = new MaintenanceState(new DateTimeImmutable('2026-11-08 12:00:00 UTC'), 300);

        self::assertSame($fallback, MaintenanceRecord::decode($record, $fallback));
    }

    public function testARecordOfNoDelay(): void
    {
        $fallback = new MaintenanceState(new DateTimeImmutable('2026-11-08 12:00:00 UTC'), 300);

        self::assertEquals(new MaintenanceState(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), 0), MaintenanceRecord::decode(['since' => '2026-11-08T10:00:00+00:00', 'retry_after' => 0], $fallback));
    }
}
