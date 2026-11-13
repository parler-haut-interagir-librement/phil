<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Infrastructure;

use Phil\MonitoringBundle\Domain\Status\HeartbeatState;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\MonitoringBundle\Domain\ValueObject\HeartbeatStatus;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Phil\StatusPageBundle\Infrastructure\Source\DeclaredStatuses;
use Phil\StatusPageBundle\Infrastructure\Source\MonitoringStatuses;
use Phil\StatusPageBundle\Tests\Unit\Support\FakeMonitoring;
use Phil\StatusPageBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversClass(DeclaredStatuses::class)]
#[CoversClass(MonitoringStatuses::class)]
final class SourcesTest extends TestCase
{
    public function testTheDeclaredStatesAreThoseOfTheConfiguration(): void
    {
        $declared = DeclaredStatuses::fromValues(['api' => 'degraded', 'website' => 'operational']);

        self::assertSame(['api' => ComponentStatus::Degraded, 'website' => ComponentStatus::Operational], $declared->statuses(Fixtures::components()));
    }

    public function testEachComponentTakesTheWorstOfItsChecksAndTasks(): void
    {
        $monitoring = new FakeMonitoring($this->report(['database' => 'up', 'cache' => 'degraded', 'queue' => 'down']), [
            new HeartbeatStatus('backup', HeartbeatState::Late, Fixtures::at('-2 days'), Fixtures::at('-1 day')),
            new HeartbeatStatus('report', HeartbeatState::OnTime, Fixtures::at('-1 hour'), Fixtures::at('+1 hour')),
            new HeartbeatStatus('cleanup', HeartbeatState::Never, null, null),
        ]);
        $components = [
            new Component('up', 'Up'),
            new Component('degraded', 'Degraded'),
            new Component('down', 'Down'),
            new Component('late', 'Late'),
            new Component('on-time', 'On time'),
            new Component('never', 'Never beat'),
            new Component('unknown', 'Unknown'),
            new Component('unwatched', 'Unwatched'),
        ];
        $statuses = new MonitoringStatuses(
            $monitoring,
            new DeclaredStatuses(['unknown' => ComponentStatus::PartialOutage, 'unwatched' => ComponentStatus::Degraded, 'up' => ComponentStatus::MajorOutage, 'never' => ComponentStatus::Degraded]),
            ['up' => ['database'], 'degraded' => ['database', 'cache'], 'down' => ['cache', 'queue', 'database'], 'unknown' => ['nothing']],
            ['late' => ['report', 'backup'], 'on-time' => ['report'], 'never' => ['cleanup'], 'unknown' => ['nothing']],
        );

        self::assertSame([
            'up' => ComponentStatus::Operational,
            'degraded' => ComponentStatus::Degraded,
            'down' => ComponentStatus::MajorOutage,
            'late' => ComponentStatus::Degraded,
            'on-time' => ComponentStatus::Operational,
            'never' => ComponentStatus::Operational,
            'unknown' => ComponentStatus::PartialOutage,
            'unwatched' => ComponentStatus::Degraded,
        ], $statuses->statuses($components));
    }

    public function testTheMaintenanceModePutsEveryComponentUnderMaintenance(): void
    {
        $monitoring = new FakeMonitoring($this->report(['database' => 'down']), [], new MaintenanceState(Fixtures::now(), 60));
        $statuses = new MonitoringStatuses($monitoring, new DeclaredStatuses([]), ['website' => ['database']], []);

        self::assertSame(['website' => ComponentStatus::Maintenance, 'api' => ComponentStatus::Maintenance, 'sign-in' => ComponentStatus::Maintenance], $statuses->statuses(Fixtures::components()));
    }

    public function testTheChecksRunOncePerTtlWithAPool(): void
    {
        $monitoring = new FakeMonitoring($this->report(['database' => 'down']));
        $pool = new ArrayAdapter();
        $statuses = new MonitoringStatuses($monitoring, new DeclaredStatuses([]), ['website' => ['database']], [], $pool, 30);

        self::assertSame(ComponentStatus::MajorOutage, $statuses->statuses(Fixtures::components())['website']);
        $monitoring->report = $this->report(['database' => 'up']);
        self::assertSame(ComponentStatus::MajorOutage, $statuses->statuses(Fixtures::components())['website']);
        self::assertSame(1, $monitoring->rounds);
        self::assertTrue($pool->hasItem(MonitoringStatuses::CACHE_KEY));
        self::assertSame(['website' => 'major_outage', 'api' => 'operational', 'sign-in' => 'operational'], $pool->getItem(MonitoringStatuses::CACHE_KEY)->get());
    }

    public function testTheCacheExpiresAfterTheTtl(): void
    {
        $monitoring = new FakeMonitoring($this->report([]));
        $pool = new ArrayAdapter();
        new MonitoringStatuses($monitoring, new DeclaredStatuses([]), [], [], $pool, 30)->statuses(Fixtures::components());

        $expiry = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($pool);
        self::assertIsArray($expiry);
        $at = array_first($expiry);
        self::assertIsFloat($at);
        self::assertEqualsWithDelta(microtime(true) + 30, $at, 2);
    }

    public function testWithoutPoolOrWithATtlOf0TheChecksRunEachTime(): void
    {
        $monitoring = new FakeMonitoring($this->report([]));
        $pool = new ArrayAdapter();

        new MonitoringStatuses($monitoring, new DeclaredStatuses([]), [], [], null, 30)->statuses([]);
        new MonitoringStatuses($monitoring, new DeclaredStatuses([]), [], [], null, 30)->statuses([]);
        $noTtl = new MonitoringStatuses($monitoring, new DeclaredStatuses([]), [], [], $pool, 0);
        $noTtl->statuses([]);
        $noTtl->statuses([]);

        self::assertSame(4, $monitoring->rounds);
        self::assertFalse($pool->hasItem(MonitoringStatuses::CACHE_KEY));
    }

    /**
     * @param array<string, 'up'|'degraded'|'down'> $checks
     */
    private function report(array $checks): HealthReport
    {
        $results = [];

        foreach ($checks as $name => $status) {
            $results[] = new CheckResult($name, match ($status) {
                'up' => CheckOutcome::up(),
                'degraded' => CheckOutcome::degraded('slow'),
                'down' => CheckOutcome::down('gone'),
            }, 1);
        }

        return new HealthReport($results, Fixtures::now());
    }

    public function testTheStatesAreKeptSixtySecondsByDefault(): void
    {
        $pool = new ArrayAdapter();
        new MonitoringStatuses(new FakeMonitoring($this->report([])), new DeclaredStatuses([]), [], [], $pool)->statuses(Fixtures::components());

        $expiry = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($pool);
        self::assertIsArray($expiry);
        $at = array_first($expiry);
        self::assertIsFloat($at);
        self::assertEqualsWithDelta(microtime(true) + 60, $at, 0.9);
    }

    public function testATtlOf0NeverAsksThePool(): void
    {
        $pool = $this->createMock(CacheInterface::class);
        $pool->expects(self::never())->method('get');

        new MonitoringStatuses(new FakeMonitoring($this->report([])), new DeclaredStatuses([]), [], [], $pool, 0)->statuses([]);
    }
}
