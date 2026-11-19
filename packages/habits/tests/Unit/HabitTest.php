<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Habits\CheckIn;
use Phil\Habits\CheckInStatus;
use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidCheckIn;
use Phil\Habits\Exception\InvalidHabit;
use Phil\Habits\Habit;
use Phil\Habits\Schedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Habit::class)]
#[CoversClass(CheckIn::class)]
#[CoversClass(CheckInStatus::class)]
#[CoversClass(InvalidHabit::class)]
#[CoversClass(InvalidCheckIn::class)]
final class HabitTest extends TestCase
{
    public function testAHabitKeepsWhatItIsGiven(): void
    {
        $habit = $this->habit(target: 8);

        self::assertSame('habit-1', $habit->id);
        self::assertSame('member-1', $habit->member);
        self::assertSame('Drink water', $habit->name);
        self::assertSame('daily', $habit->schedule->toString());
        self::assertSame('America/Toronto', $habit->timeZone->getName());
        self::assertSame('2026-11-13', $habit->startsOn->toString());
        self::assertSame(8, $habit->target);
        self::assertSame(8, $habit->unitsPerDay());
        self::assertNull($habit->archivedOn);
        self::assertSame(1, $this->habit()->unitsPerDay());
    }

    public function testAHabitIsArchivedOnItsLastDay(): void
    {
        $archived = $this->habit(target: 8)->archive(Day::fromString('2026-11-20'));

        self::assertSame('2026-11-20', $archived->archivedOn?->toString());
        self::assertSame(8, $archived->target);
        self::assertSame('habit-1', $archived->id);
        self::assertSame('2026-11-13', $this->habit()->archive(Day::fromString('2026-11-13'))->archivedOn?->toString());
    }

    public function testAHabitChangesItsNameScheduleOrTarget(): void
    {
        $habit = $this->habit(target: 8)->archive(Day::fromString('2026-11-20'));

        $renamed = $habit->change('Water');
        self::assertSame('Water', $renamed->name);
        self::assertSame(8, $renamed->target);
        self::assertSame('2026-11-20', $renamed->archivedOn?->toString());
        self::assertSame('2026-11-13', $renamed->startsOn->toString());
        self::assertSame('America/Toronto', $renamed->timeZone->getName());
        self::assertSame('member-1', $renamed->member);

        $changed = $habit->change(schedule: Schedule::perWeek(3), target: 4);
        self::assertSame('Drink water', $changed->name);
        self::assertSame('3 per week', $changed->schedule->toString());
        self::assertSame(4, $changed->target);

        self::assertNull($habit->change(withoutTarget: true)->target);
        self::assertSame('daily', $habit->change(withoutTarget: true)->schedule->toString());
    }

    /**
     * @return iterable<string, array{array{id?: string, member?: string, name?: string, target?: int, archivedOn?: Day}, string}>
     */
    public static function invalidHabits(): iterable
    {
        $identifier = static fn (string $what): string => \sprintf('The identifier of a %s is 1 to 255 visible ASCII characters.', $what);
        $name = 'The name of a habit is 1 to 200 characters, without surrounding spaces or control characters.';
        $target = 'The target of a habit is a whole number from 2 to 1000000, or none.';

        yield 'an empty identifier' => [['id' => ''], $identifier('habit')];
        yield 'an identifier with a space' => [['id' => 'habit 1'], $identifier('habit')];
        yield 'an identifier too long' => [['id' => str_repeat('h', 256)], $identifier('habit')];
        yield 'an identifier with a new line after' => [['id' => "habit\n"], $identifier('habit')];
        yield 'an accented identifier' => [['id' => 'hàbit'], $identifier('habit')];
        yield 'an empty member' => [['member' => ''], $identifier('member')];
        yield 'an email address' => [['member' => 'ada@example.com'], 'A member is named by an opaque identifier, never by an email address.'];
        yield 'an empty name' => [['name' => ''], $name];
        yield 'a name with spaces around' => [['name' => ' Read'], $name];
        yield 'a name with a space after' => [['name' => 'Read '], $name];
        yield 'a name with a tab inside' => [['name' => "Read\tbooks"], $name];
        yield 'a name too long' => [['name' => str_repeat('é', 201)], $name];
        yield 'a target of one' => [['target' => 1], $target];
        yield 'a target too high' => [['target' => 1_000_001], $target];
        yield 'archived before its first day' => [['archivedOn' => Day::fromString('2026-11-12')], 'A habit is archived on or after its first day.'];
    }

    /**
     * @param array{id?: string, member?: string, name?: string, target?: int, archivedOn?: Day} $values
     */
    #[DataProvider('invalidHabits')]
    public function testAHabitOutOfBoundsIsRefused(array $values, string $message): void
    {
        $this->expectExceptionObject(InvalidHabit::because($message));

        $this->habit(...$values);
    }

    public function testTheBoundsOfAHabit(): void
    {
        $habit = $this->habit(id: str_repeat('h', 255), member: '~!', name: str_repeat('é', 200), target: 1_000_000);

        self::assertSame(255, \strlen($habit->id));
        self::assertSame(2, $this->habit(target: 2)->target);
        self::assertSame('Lire 読書', $this->habit(name: 'Lire 読書')->name);
    }

    public function testACheckInIsRecordedInUtc(): void
    {
        $checkIn = new CheckIn('habit-1', Day::fromString('2026-11-13'), CheckInStatus::Partial, 5, new DateTimeImmutable('2026-11-13 20:15:00', new DateTimeZone('America/Toronto')));

        self::assertSame('2026-11-14 01:15:00 UTC', $checkIn->recordedAt->format('Y-m-d H:i:s T'));
        self::assertSame('habit-1', $checkIn->habit);
        self::assertSame(5, $checkIn->value);
    }

    public function testTheUnitsOfACheckIn(): void
    {
        $day = Day::fromString('2026-11-13');
        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');

        self::assertSame(8, new CheckIn('h', $day, CheckInStatus::Done, null, $at)->units(8));
        self::assertSame(8, new CheckIn('h', $day, CheckInStatus::Done, 3, $at)->units(8));
        self::assertSame(5, new CheckIn('h', $day, CheckInStatus::Partial, 5, $at)->units(8));
        self::assertSame(8, new CheckIn('h', $day, CheckInStatus::Partial, 12, $at)->units(8));
        self::assertSame(0, new CheckIn('h', $day, CheckInStatus::Partial, 0, $at)->units(8));
        self::assertSame(0, new CheckIn('h', $day, CheckInStatus::Skipped, null, $at)->units(8));
        self::assertSame(0, new CheckIn('h', $day, CheckInStatus::Frozen, null, $at)->units(8));
        self::assertSame(1_000_000_000, new CheckIn('h', $day, CheckInStatus::Partial, 1_000_000_000, $at)->value);
    }

    public function testExcusedStatuses(): void
    {
        self::assertFalse(CheckInStatus::Done->isExcused());
        self::assertFalse(CheckInStatus::Partial->isExcused());
        self::assertTrue(CheckInStatus::Skipped->isExcused());
        self::assertTrue(CheckInStatus::Frozen->isExcused());
    }

    /**
     * @return iterable<string, array{CheckInStatus, ?int, string}>
     */
    public static function invalidCheckIns(): iterable
    {
        $value = 'The value of a check-in is a whole number from 0 to a thousand times the largest target.';

        yield 'a negative value' => [CheckInStatus::Done, -1, $value];
        yield 'a value too high' => [CheckInStatus::Partial, 1_000_000_001, $value];
        yield 'a partial without value' => [CheckInStatus::Partial, null, 'A partial check-in carries the value done.'];
        yield 'a skipped day with a value' => [CheckInStatus::Skipped, 0, 'A skipped or frozen day carries no value.'];
        yield 'a frozen day with a value' => [CheckInStatus::Frozen, 3, 'A skipped or frozen day carries no value.'];
    }

    #[DataProvider('invalidCheckIns')]
    public function testACheckInOutOfBoundsIsRefused(CheckInStatus $status, ?int $value, string $message): void
    {
        $this->expectExceptionObject(InvalidCheckIn::because($message));

        new CheckIn('h', Day::fromString('2026-11-13'), $status, $value, new DateTimeImmutable());
    }

    private function habit(
        string $id = 'habit-1',
        string $member = 'member-1',
        string $name = 'Drink water',
        ?int $target = null,
        ?Day $archivedOn = null,
    ): Habit {
        return new Habit($id, $member, $name, Schedule::daily(), new DateTimeZone('America/Toronto'), Day::fromString('2026-11-13'), $target, $archivedOn);
    }
}
