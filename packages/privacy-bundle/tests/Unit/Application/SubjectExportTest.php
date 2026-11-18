<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use Phil\PrivacyBundle\Application\Exception\LockLost;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ExpiringLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\InterruptingModule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;

#[CoversClass(SubjectExport::class)]
#[CoversClass(HeldLock::class)]
#[CoversClass(MisconfiguredPrivacy::class)]
final class SubjectExportTest extends TestCase
{
    public function testItHasOneSectionPerModuleSortedByName(): void
    {
        $export = new SubjectExport([new FixedExporter('newsletter', ['lists' => ['weekly']]), new FixedExporter('identity', ['email' => 'a@example.com'])]);

        self::assertSame(
            [
                'identity' => ['subject' => 's-1', 'email' => 'a@example.com'],
                'newsletter' => ['subject' => 's-1', 'lists' => ['weekly']],
            ],
            $export->export(DataSubject::fromString('s-1')),
        );
    }

    public function testNoModuleMeansAnEmptyExport(): void
    {
        self::assertSame([], new SubjectExport([])->export(DataSubject::fromString('s-1')));
    }

    public function testTwoModulesUnderOneNameAreRefused(): void
    {
        $this->expectException(MisconfiguredPrivacy::class);
        $this->expectExceptionMessageIsOrContains('"identity"');

        new SubjectExport([new FixedExporter('identity', []), new FixedExporter('identity', [])]);
    }

    public function testALostLockStopsTheExportBeforeTheNextModule(): void
    {
        [$lock, $first] = $this->lockTakenDuring('billing');
        $after = new InterruptingModule('identity', static function (): void {
        });

        try {
            new SubjectExport([$first, $after])->export(DataSubject::fromString('s-1'), $lock);
            self::fail('An export that lost its lock goes no further.');
        } catch (LockLost) {
            self::assertSame(1, $first->calls);
            self::assertSame(0, $after->calls);
        }
    }

    /**
     * A lock of one minute, held, and a step that outlasts it while another worker takes it.
     *
     * @return array{HeldLock, InterruptingModule}
     */
    private function lockTakenDuring(string $module): array
    {
        $clock = new MockClock('2026-11-17T00:00:00Z');
        $locks = new LockFactory(new ExpiringLockStore($clock));
        $lock = $locks->createLock('work', 60.0, false);
        self::assertTrue($lock->acquire());

        return [new HeldLock($lock), new InterruptingModule($module, static function () use ($clock, $locks): void {
            $clock->sleep(61);
            self::assertTrue($locks->createLock('work', 60.0, false)->acquire());
        })];
    }
}
