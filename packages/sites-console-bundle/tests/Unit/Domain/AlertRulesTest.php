<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Status\AlertKind;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\Alert;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertRules::class)]
#[CoversClass(Alert::class)]
final class AlertRulesTest extends TestCase
{
    public function testNoSnapshotNoAlert(): void
    {
        self::assertSame([], new AlertRules()->evaluate('shop', null, []));
    }

    public function testAnUnreachableSiteHasOneAlert(): void
    {
        $alerts = new AlertRules(['*'])->evaluate('shop', Snapshot::unreachable('shop', 'timeout', Fixtures::now()), ['phil/url' => '9.9']);

        self::assertEquals([new Alert(AlertKind::Unreachable, 'shop', '', 'timeout')], $alerts);
        self::assertSame('shop|unreachable|', $alerts[0]->key());
    }

    public function testHealthAlerts(): void
    {
        $rules = new AlertRules();

        self::assertEquals([new Alert(AlertKind::HealthDown, 'shop')], $rules->evaluate('shop', $this->snapshot([], HealthState::Down), []));
        self::assertEquals([new Alert(AlertKind::HealthDegraded, 'shop')], $rules->evaluate('shop', $this->snapshot([], HealthState::Degraded), []));
        self::assertSame([], $rules->evaluate('shop', $this->snapshot([], HealthState::Unknown), []));
        self::assertSame([], $rules->evaluate('shop', $this->snapshot([], HealthState::Up), []));
    }

    public function testAWatchedPackageBehindTheNewestOfTheFleet(): void
    {
        $rules = new AlertRules(['phil/*']);
        $newest = $rules->newest([
            $this->snapshot(['phil/url' => '0.1.4', 'other/lib' => '9.0', 'phil/enums' => 'dev-main']),
            $this->snapshot(['phil/url' => '0.1.6', 'phil/enums' => '0.3.0']),
            $this->snapshot(['phil/url' => '0.1.5', 'phil/enums' => '0.2.0']),
            Snapshot::unreachable('down', 'x', Fixtures::now()),
        ]);

        self::assertSame(['phil/enums' => '0.3.0', 'phil/url' => '0.1.6'], $newest);
        self::assertEquals(
            [new Alert(AlertKind::VersionBehind, 'shop', 'phil/url', '0.1.4 < 0.1.6')],
            $rules->evaluate('shop', $this->snapshot(['phil/url' => '0.1.4', 'other/lib' => '1.0', 'phil/enums' => 'dev-main']), $newest),
        );
        self::assertSame([], $rules->evaluate('shop', $this->snapshot(['phil/url' => '0.1.6']), $newest));
        self::assertSame('shop|version_behind|phil/url', $rules->evaluate('shop', $this->snapshot(['phil/url' => '0.1.4']), $newest)[0]->key());
        self::assertTrue($rules->isWatched('phil/url'));
        self::assertFalse($rules->isWatched('other/lib'));
    }

    public function testAReleaseBeatsABranchWhichHasNoOrder(): void
    {
        $rules = new AlertRules(['*']);

        self::assertSame(['a/b' => '1.0'], $rules->newest([$this->snapshot(['a/b' => '1.0']), $this->snapshot(['a/b' => 'dev-main'])]));
        self::assertSame(['a/b' => 'dev-main'], $rules->newest([$this->snapshot(['a/b' => 'dev-main']), $this->snapshot(['a/b' => 'dev-next'])]));
        self::assertSame(['a/b' => '1.0', 'c/d' => '2.0'], $rules->newest([$this->snapshot(['c/d' => '2.0', 'a/b' => '1.0'])]));
    }

    public function testAnExpectedVersion(): void
    {
        $rules = new AlertRules(['phil/*'], ['phil/url' => '0.1.5', 'symfony/http-kernel' => '8.2.1']);
        $snapshot = $this->snapshot(['phil/url' => '0.1.4', 'symfony/http-kernel' => '8.2.0']);

        self::assertEquals([
            new Alert(AlertKind::VersionBehind, 'shop', 'phil/url', '0.1.4 < 0.1.5'),
            new Alert(AlertKind::VersionBehind, 'shop', 'symfony/http-kernel', '8.2.0 < 8.2.1'),
        ], $rules->evaluate('shop', $snapshot, ['phil/url' => '0.1.4']));

        // The newer of the expected version and the fleet's is the target.
        self::assertSame('0.1.4 < 0.1.7', $rules->evaluate('shop', $snapshot, ['phil/url' => '0.1.7'])[0]->detail);
        self::assertSame([], new AlertRules([], ['phil/url' => '0.1.4'])->evaluate('shop', $this->snapshot(['phil/url' => '0.1.4']), ['phil/url' => '0.1.9']));
        self::assertSame([], new AlertRules([], ['phil/url' => 'dev-main'])->evaluate('shop', $this->snapshot(['phil/url' => '0.1.4']), []));
        self::assertSame('0.1.4 < 0.1.5', new AlertRules(['phil/*'], ['phil/url' => '0.1.5'])->evaluate('shop', $this->snapshot(['phil/url' => '0.1.4']), ['phil/url' => 'dev-main'])[0]->detail);
    }

    /**
     * @param array<string, string> $packages
     */
    private function snapshot(array $packages, HealthState $health = HealthState::Up): Snapshot
    {
        return Snapshot::reached('shop', Fixtures::status('shop', $packages, $health), Fixtures::now());
    }
}
