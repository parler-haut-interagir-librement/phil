<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Rotation;

use DateTimeImmutable;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The grandfather-father-son scheme over the times of the file names.
 */
#[CoversClass(RotationPolicy::class)]
final class RotationPolicyTest extends TestCase
{
    public function testEachRuleKeepsTheMostRecentBackupOfItsPeriods(): void
    {
        $sets = [
            $this->set('2026-09-30 23:00'),
            $this->set('2026-10-31 22:00'),
            $this->set('2026-11-01 01:00'),
            $this->set('2026-11-02 02:00'),
            $this->set('2026-11-08 03:00'),
            $this->set('2026-11-09 03:00'),
            $this->set('2026-11-09 15:00'),
            $this->set('2026-11-09 15:00', 'before-restore'),
            $this->set('2026-11-10 03:00'),
        ];

        self::assertSame(['2026-09-30 23:00', '2026-10-31 22:00', '2026-11-01 01:00', '2026-11-02 02:00', '2026-11-08 03:00', '2026-11-09 03:00'], $this->times(new RotationPolicy(last: 2)->expired($sets)));
        self::assertSame(['2026-09-30 23:00', '2026-10-31 22:00', '2026-11-01 01:00', '2026-11-02 02:00', '2026-11-08 03:00', '2026-11-09 03:00'], $this->times(new RotationPolicy(daily: 2)->expired($sets)));
        self::assertSame(['2026-09-30 23:00', '2026-10-31 22:00', '2026-11-01 01:00', '2026-11-02 02:00', '2026-11-09 03:00', '2026-11-09 15:00'], $this->times(new RotationPolicy(weekly: 2)->expired($sets)), 'The ISO week of 2026-11-08, a Sunday, is that of 2026-11-02.');
        self::assertSame(['2026-11-01 01:00', '2026-11-02 02:00', '2026-11-08 03:00', '2026-11-09 03:00', '2026-11-09 15:00'], $this->times(new RotationPolicy(monthly: 5)->expired($sets)), 'Only three months have a backup.');
        self::assertSame(['2026-11-01 01:00', '2026-11-02 02:00', '2026-11-09 03:00'], $this->times(new RotationPolicy(last: 1, daily: 2, weekly: 2, monthly: 3)->expired($sets)));
        self::assertSame([], new RotationPolicy(last: 100)->expired($sets));
        self::assertSame([], new RotationPolicy(last: 1)->expired([$this->set('2026-11-10 03:00', 'anonymized')]), 'A labelled set is never rotated.');
    }

    public function testEqualTimesAreOrderedByStem(): void
    {
        $sets = [$this->set('2026-11-10 03:00', '', 'b'), $this->set('2026-11-10 03:00', '', 'a'), $this->set('2026-11-10 03:00', '', 'c')];

        self::assertSame(['a-2026-11-10 03:00', 'b-2026-11-10 03:00'], array_map(static fn (BackupSet $set): string => $set->stem, new RotationPolicy(last: 1)->expired($sets)));
    }

    public function testEqualTimesAreOrderedByCopyNumberFirst(): void
    {
        $at = new DateTimeImmutable('2026-11-10 03:00 UTC');
        $sets = [new BackupSet('app-20261110-030000_2', $at, '', 'b.sql', null, 2), new BackupSet('app-20261110-030000_10', $at, '', 'c.sql', null, 10), new BackupSet('app-20261110-030000', $at, '', 'a.sql', null)];

        self::assertSame(['app-20261110-030000', 'app-20261110-030000_2'], array_map(static fn (BackupSet $set): string => $set->stem, new RotationPolicy(last: 1)->expired($sets)));
    }

    public function testARotationKeepsSomething(): void
    {
        foreach ([[0, 0, 0, 0], [-1, 1, 0, 0], [1, -1, 0, 0], [1, 0, -1, 0], [1, 0, 0, -1]] as [$last, $daily, $weekly, $monthly]) {
            try {
                new RotationPolicy($last, $daily, $weekly, $monthly);
                self::fail(implode(',', [$last, $daily, $weekly, $monthly]));
            } catch (InvalidBackupValue $invalidBackupValue) {
                self::assertSame('A rotation keeps zero or more backups by each rule, and at least one in all.', $invalidBackupValue->getMessage());
            }
        }

        self::assertSame(2, new RotationPolicy(2, 0, 0, 0)->last);
        $last = new RotationPolicy(last: 1);
        self::assertSame([1, 0, 0, 0], [$last->last, $last->daily, $last->weekly, $last->monthly]);
        self::assertSame([0, 0, 0, 1], [new RotationPolicy(monthly: 1)->last, new RotationPolicy(monthly: 1)->daily, new RotationPolicy(monthly: 1)->weekly, new RotationPolicy(monthly: 1)->monthly]);
    }

    private function set(string $time, string $label = '', string $name = 'app'): BackupSet
    {
        return new BackupSet($name.'-'.$time.('' === $label ? '' : '-'.$label), new DateTimeImmutable($time.' UTC'), $label, $time.'.sql', null);
    }

    /**
     * @param list<BackupSet> $sets
     *
     * @return list<string>
     */
    private function times(array $sets): array
    {
        return array_map(static fn (BackupSet $set): string => $set->time->format('Y-m-d H:i'), $sets);
    }
}
