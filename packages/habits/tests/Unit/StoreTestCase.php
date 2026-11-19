<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Habits\CheckIn;
use Phil\Habits\CheckInStatus;
use Phil\Habits\Contract\CheckInStore;
use Phil\Habits\Contract\HabitStore;
use Phil\Habits\Day;
use Phil\Habits\Habit;
use Phil\Habits\Schedule;
use PHPUnit\Framework\TestCase;

/**
 * The contract of both stores, run against each adapter: habits and check-ins kept, read back, replaced and forgotten.
 */
abstract class StoreTestCase extends TestCase
{
    abstract protected function habitStore(): HabitStore;

    abstract protected function checkInStore(): CheckInStore;

    public function testAHabitIsKeptAndReadBack(): void
    {
        $store = $this->habitStore();
        $habit = new Habit('habit-1', 'member-1', 'Lire 読書 « chaque jour »', Schedule::parse('on mon,wed,fri'), new DateTimeZone('America/Toronto'), Day::fromString('2026-11-02'), 30, Day::fromString('2026-12-31'));
        $store->save($habit);

        self::assertEquals($habit, $store->get('habit-1'));
        self::assertNull($store->get('habit-2'));

        $plain = new Habit('habit-2', 'member-1', 'Walk', Schedule::daily(), new DateTimeZone('UTC'), Day::fromString('2026-11-02'));
        $store->save($plain);
        self::assertEquals($plain, $store->get('habit-2'));
    }

    public function testAHabitIsReplaced(): void
    {
        $store = $this->habitStore();
        $habit = new Habit('habit-1', 'member-1', 'Read', Schedule::daily(), new DateTimeZone('UTC'), Day::fromString('2026-11-02'));
        $store->save($habit);
        $store->save($habit->change('Read more', Schedule::perMonth(10), 20)->archive(Day::fromString('2026-11-30')));

        $kept = $store->get('habit-1');
        self::assertSame('Read more', $kept?->name);
        self::assertSame('10 per month', $kept->schedule->toString());
        self::assertSame(20, $kept->target);
        self::assertSame('2026-11-30', $kept->archivedOn?->toString());
        self::assertCount(1, $store->ofMember('member-1'));
    }

    public function testIdentifiersAreComparedByteForByte(): void
    {
        $store = $this->habitStore();
        $store->save(new Habit('habit-a', 'member-1', 'Lower', Schedule::daily(), new DateTimeZone('UTC'), Day::fromString('2026-11-02')));
        $store->save(new Habit('HABIT-A', 'MEMBER-1', 'Upper', Schedule::daily(), new DateTimeZone('UTC'), Day::fromString('2026-11-02')));

        self::assertSame('Lower', $store->get('habit-a')?->name);
        self::assertSame('Upper', $store->get('HABIT-A')?->name);
        self::assertSame(['habit-a'], array_map(static fn (Habit $habit): string => $habit->id, $store->ofMember('member-1')));
    }

    public function testTheHabitsOfAMemberAreListedByIdentifier(): void
    {
        $store = $this->habitStore();

        foreach (['habit-b' => 'member-1', 'habit-a' => 'member-1', 'habit-c' => 'member-2'] as $id => $member) {
            $store->save(new Habit($id, $member, 'Read', Schedule::daily(), new DateTimeZone('UTC'), Day::fromString('2026-11-02')));
        }

        self::assertSame(['habit-a', 'habit-b'], array_map(static fn (Habit $habit): string => $habit->id, $store->ofMember('member-1')));
        self::assertSame([], $store->ofMember('member-3'));

        $store->remove('habit-a');
        $store->remove('habit-z');
        self::assertNull($store->get('habit-a'));
        self::assertSame(['habit-b'], array_map(static fn (Habit $habit): string => $habit->id, $store->ofMember('member-1')));
    }

    public function testACheckInIsKeptAndReadBack(): void
    {
        $store = $this->checkInStore();
        $checkIn = new CheckIn('habit-1', Day::fromString('2026-11-02'), CheckInStatus::Partial, 5, new DateTimeImmutable('2026-11-02 23:30:00', new DateTimeZone('America/Toronto')));
        $store->save($checkIn);
        $store->save(new CheckIn('habit-1', Day::fromString('2026-11-03'), CheckInStatus::Frozen, null, new DateTimeImmutable('@1700000000')));

        $kept = $store->get('habit-1', Day::fromString('2026-11-02'));
        self::assertNotNull($kept);
        self::assertSame(CheckInStatus::Partial, $kept->status);
        self::assertSame(5, $kept->value);
        self::assertSame('2026-11-03 04:30:00 +00:00', $kept->recordedAt->format('Y-m-d H:i:s P'));
        self::assertSame('habit-1', $kept->habit);
        self::assertNull($store->get('habit-1', Day::fromString('2026-11-04')));
        self::assertNull($store->get('habit-2', Day::fromString('2026-11-02')));

        $frozen = $store->get('habit-1', Day::fromString('2026-11-03'));
        self::assertSame(CheckInStatus::Frozen, $frozen?->status);
        self::assertNull($frozen->value);
        self::assertSame(1_700_000_000, $frozen->recordedAt->getTimestamp());
    }

    public function testACheckInReplacesTheOneOfItsDay(): void
    {
        $store = $this->checkInStore();
        $day = Day::fromString('2026-11-02');
        $store->save(new CheckIn('habit-1', $day, CheckInStatus::Skipped, null, new DateTimeImmutable('@1700000000')));
        $store->save(new CheckIn('habit-1', $day, CheckInStatus::Done, 3, new DateTimeImmutable('@1700000100')));

        self::assertCount(1, $store->between('habit-1', $day, $day));
        self::assertSame(CheckInStatus::Done, $store->get('habit-1', $day)?->status);
        self::assertSame(3, $store->get('habit-1', $day)->value);
    }

    public function testTheCheckInsOfARangeComeInOrder(): void
    {
        $store = $this->checkInStore();

        foreach (['2026-11-05', '2026-11-01', '2026-11-03', '2026-10-31', '2026-11-06'] as $day) {
            $store->save(new CheckIn('habit-1', Day::fromString($day), CheckInStatus::Done, null, new DateTimeImmutable('@1700000000')));
        }

        $store->save(new CheckIn('habit-2', Day::fromString('2026-11-02'), CheckInStatus::Done, null, new DateTimeImmutable('@1700000000')));

        $days = array_map(static fn (CheckIn $checkIn): string => $checkIn->day->toString(), $store->between('habit-1', Day::fromString('2026-11-01'), Day::fromString('2026-11-05')));
        self::assertSame(['2026-11-01', '2026-11-03', '2026-11-05'], $days);
        self::assertSame([], $store->between('habit-3', Day::fromString('2026-11-01'), Day::fromString('2026-11-05')));
    }

    public function testCheckInsAreForgotten(): void
    {
        $store = $this->checkInStore();

        foreach (['habit-1', 'habit-2'] as $habit) {
            foreach (['2026-11-01', '2026-11-02'] as $day) {
                $store->save(new CheckIn($habit, Day::fromString($day), CheckInStatus::Done, null, new DateTimeImmutable('@1700000000')));
            }
        }

        $store->remove('habit-1', Day::fromString('2026-11-01'));
        $store->remove('habit-1', Day::fromString('2026-11-09'));
        self::assertNull($store->get('habit-1', Day::fromString('2026-11-01')));
        self::assertNotNull($store->get('habit-1', Day::fromString('2026-11-02')));
        self::assertNotNull($store->get('habit-2', Day::fromString('2026-11-01')));

        $store->removeHabit('habit-1');
        self::assertSame([], $store->between('habit-1', Day::fromString('2026-11-01'), Day::fromString('2026-11-02')));
        self::assertCount(2, $store->between('habit-2', Day::fromString('2026-11-01'), Day::fromString('2026-11-02')));
    }
}
