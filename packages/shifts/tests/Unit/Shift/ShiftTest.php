<?php

declare(strict_types=1);

namespace Phil\Shifts\Tests\Unit\Shift;

use Closure;
use DateTimeImmutable;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Identifier;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;
use Phil\Shifts\Tests\Fixtures\Shifts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shift::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidShift::class)]
final class ShiftTest extends TestCase
{
    public function testAFixedShiftCountsTheMinutesOfItsWindowInUtc(): void
    {
        $shift = new Shift('desk', 'Info desk', ShiftKind::Fixed, new DateTimeImmutable('2026-11-28T09:00:00-05:00'), new DateTimeImmutable('2026-11-28T12:30:00-05:00'));

        self::assertSame(210, $shift->minutes);
        self::assertSame('2026-11-28 14:00:00 UTC', $shift->startsAt->format('Y-m-d H:i:s T'));
        self::assertSame('2026-11-28 17:30:00 UTC', $shift->endsAt->format('Y-m-d H:i:s T'));
        self::assertSame(1, $shift->capacity);
        self::assertSame(AssignmentMode::SelfSignUp, $shift->mode);
        self::assertSame(0, $shift->reserve);
        self::assertNull($shift->requiredRole);
        self::assertFalse($shift->critical);
        self::assertSame(100, $shift->weight);
        self::assertFalse($shift->swapApproval);
        self::assertSame('', $shift->location);
        self::assertSame('', $shift->description);
        self::assertSame(210, $shift->weightedMinutes());
        self::assertSame(210, new Shift('desk', 'Info desk', ShiftKind::Fixed, Shifts::at('09:00:00'), Shifts::at('12:30:00'), 210)->minutes);
    }

    public function testATaskOrADurationCountsItsOwnMinutes(): void
    {
        self::assertSame(120, Shifts::cloakroom()->minutes);
        self::assertSame(180, Shifts::cloakroom()->weightedMinutes());
        self::assertSame(5, new Shift('t', 'Tables', ShiftKind::Task, Shifts::at('08:00:00'), Shifts::at('08:05:00'), 5)->minutes);
        self::assertSame(1440, new Shift('t', 'Tables', ShiftKind::Task, Shifts::at('00:00:00'), new DateTimeImmutable('2026-11-30T00:00:00Z'), 1440)->minutes);
    }

    public function testTheWeightedMinutesRoundHalfUp(): void
    {
        $shift = static fn (int $minutes, int $weight): Shift => new Shift('t', 'T', ShiftKind::Duration, Shifts::at('08:00:00'), Shifts::at('20:00:00'), $minutes, weight: $weight);

        self::assertSame(8, $shift(5, 150)->weightedMinutes());
        self::assertSame(7, $shift(7, 101)->weightedMinutes());
        self::assertSame(1, $shift(5, 10)->weightedMinutes());
        self::assertSame(50, $shift(5, 1000)->weightedMinutes());
        self::assertSame(3, $shift(5, 50)->weightedMinutes());
        self::assertSame(2, $shift(6, 33)->weightedMinutes());
        self::assertSame(7, $shift(7, 107)->weightedMinutes());
    }

    /**
     * @return iterable<string, array{Closure(): Shift, string}>
     */
    public static function invalidShifts(): iterable
    {
        $at = Shifts::at(...);

        yield 'identifier' => [static fn (): Shift => new Shift('Desk', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00')), 'The shift is 1 to 64'];
        yield 'empty title' => [static fn (): Shift => new Shift('d', '  ', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00')), 'The title is a text of 200'];
        yield 'long title' => [static fn (): Shift => new Shift('d', str_repeat('a', 201), ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00')), 'The title'];
        yield 'control title' => [static fn (): Shift => new Shift('d', "a\x07", ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00')), 'The title'];
        yield 'long location' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), location: str_repeat('a', 201)), 'The location is empty or a text of 200'];
        yield 'long description' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), description: str_repeat('a', 2001)), 'The description is empty or a text of 2000'];
        yield 'role' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), requiredRole: 'first aid'), 'A role is 1 to 64'];
        yield 'window too short' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('09:04:59')), 'The window of a shift ends 5 minutes to 31 days'];
        yield 'window backwards' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('10:00:00'), $at('09:00:00')), 'The window'];
        yield 'window too long' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Task, $at('09:00:00'), $at('09:00:00')->modify('+31 days +1 minute'), 60), 'The window'];
        yield 'fixed too long' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('09:01:00')->modify('+1 day')), 'A fixed shift lasts 1,440 minutes at most.'];
        yield 'fixed other minutes' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), 59), 'A fixed shift counts the minutes of its window.'];
        yield 'task without minutes' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Task, $at('09:00:00'), $at('10:00:00')), 'A task or a duration counts 5 to 1,440'];
        yield 'task too short' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Task, $at('09:00:00'), $at('10:00:00'), 4), 'A task or a duration'];
        yield 'duration beyond window' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Duration, $at('09:00:00'), $at('10:00:00'), 61), 'A task or a duration'];
        yield 'duration too long' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Duration, $at('00:00:00'), $at('00:00:00')->modify('+2 days'), 1441), 'A task or a duration'];
        yield 'no place' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), capacity: 0), 'A shift has 1 to 1,000 places.'];
        yield 'too many places' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), capacity: 1001), 'A shift has 1 to 1,000 places.'];
        yield 'negative reserve' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), reserve: -1), 'The reserve of a shift holds 0 to 100'];
        yield 'reserve too large' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), reserve: 101), 'The reserve'];
        yield 'weight too low' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), weight: 9), 'The weight of a shift is 10 to 1,000 percent.'];
        yield 'weight too high' => [static fn (): Shift => new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), weight: 1001), 'The weight'];
    }

    /**
     * @param Closure(): Shift $build
     */
    #[DataProvider('invalidShifts')]
    public function testTheBoundsOfAShift(Closure $build, string $message): void
    {
        $this->expectException(InvalidShift::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheBoundsAreInclusive(): void
    {
        $at = Shifts::at(...);
        $shift = new Shift(str_repeat('a', 64), str_repeat('é', 200), ShiftKind::Fixed, $at('09:00:00'), $at('09:05:00'), null, 1000, AssignmentMode::Assigned, 100, str_repeat('R', 64), true, 1000, true, str_repeat('b', 200), str_repeat("c\n", 1000));

        self::assertSame(5, $shift->minutes);
        self::assertSame(1440, new Shift('d', 'T', ShiftKind::Fixed, $at('00:00:00'), $at('00:00:00')->modify('+1 day'))->minutes);
        self::assertSame(60, new Shift('d', 'T', ShiftKind::Task, $at('00:00:00'), $at('00:00:00')->modify('+31 days'), 60)->minutes);
        self::assertSame(10, new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), weight: 10)->weight);
        self::assertSame(0, new Shift('d', 'T', ShiftKind::Fixed, $at('09:00:00'), $at('10:00:00'), reserve: 0)->reserve);
        self::assertSame(60, new Shift('d', 'T', ShiftKind::Duration, $at('09:00:00'), $at('10:00:00'), 60)->minutes);
    }

    public function testTheVolunteerIdentifiers(): void
    {
        self::assertSame('ada@example.com', Identifier::volunteer('ada@example.com'));
        self::assertSame(str_repeat('é', 191), Identifier::volunteer(str_repeat('é', 191)));
        self::assertSame('Āā', Identifier::volunteer('Āā'));

        foreach (['', ' ada', 'ada ', "a\nb", str_repeat('a', 192), "\xff"] as $invalid) {
            try {
                Identifier::volunteer($invalid);
                self::fail('The volunteer is refused.');
            } catch (InvalidShift $invalidShift) {
                self::assertSame('The volunteer is 1 to 191 characters, without control characters or surrounding spaces.', $invalidShift->getMessage());
            }
        }
    }

    public function testTheOtherIdentifiers(): void
    {
        self::assertSame('0a_.-', Identifier::key('0a_.-', 'shift'));
        self::assertSame('ROLE_FIRST-AID:x.y', Identifier::role('ROLE_FIRST-AID:x.y'));
        self::assertSame("line\ttab\r\n", Identifier::text("line\ttab\r\n", 'note', 20));
        self::assertSame('', Identifier::text('', 'note', 20, false));
        self::assertSame('Āā', Identifier::text('Āā', 'note', 20));

        try {
            Identifier::role("ADMIN\n");
            self::fail('The role is refused.');
        } catch (InvalidShift) {
            self::addToAssertionCount(1);
        }

        foreach (['-a', '_a', 'a b', str_repeat('a', 65), "a\n"] as $invalid) {
            try {
                Identifier::key($invalid, 'shift');
                self::fail('The key is refused.');
            } catch (InvalidShift) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectExceptionMessageIsOrContains('The note is empty or a text of 3 characters at most');
        Identifier::text("\xff", 'note', 3, false);
    }

    public function testTwoFixedShiftsOverlapWhenTheirWindowsMeet(): void
    {
        $morning = Shifts::desk();
        $noon = Shifts::desk(id: 'noon', from: '12:00:00', to: '13:00:00');
        $inside = Shifts::desk(id: 'inside', from: '10:00:00', to: '11:00:00');
        $before = Shifts::desk(id: 'before', from: '08:00:00', to: '09:00:00');

        self::assertTrue($morning->overlaps($inside));
        self::assertTrue($inside->overlaps($morning));
        self::assertTrue($morning->overlaps(Shifts::desk(id: 'x', from: '11:59:00', to: '13:00:00')));
        self::assertTrue($morning->overlaps(Shifts::desk(id: 'y', from: '08:00:00', to: '09:01:00')));
        self::assertFalse($morning->overlaps($noon));
        self::assertFalse($noon->overlaps($morning));
        self::assertFalse($morning->overlaps($before));
        self::assertTrue($morning->overlaps($noon, 1));
        self::assertTrue($noon->overlaps($morning, 1));
        self::assertTrue($morning->overlaps($before, 1));
        self::assertFalse($morning->overlaps($noon, -10));
        self::assertTrue($morning->overlaps(Shifts::desk(id: 'z', from: '11:59:30', to: '13:00:00'), -10));
        self::assertTrue($morning->overlaps(Shifts::desk(id: 'a', from: '12:00:59', to: '13:00:00'), 1));
        self::assertFalse($morning->overlaps(Shifts::desk(id: 'b', from: '12:01:00', to: '13:00:00'), 1));
        self::assertFalse(Shifts::desk(id: 'b', from: '12:01:00', to: '13:00:00')->overlaps($morning, 1));
        self::assertTrue(Shifts::desk(id: 'b', from: '12:00:59', to: '13:00:00')->overlaps($morning, 1));
        self::assertFalse($morning->overlaps(Shifts::cloakroom()));
        self::assertFalse(Shifts::cloakroom()->overlaps($morning));
    }

    public function testWhenAShiftIsOpenAndOver(): void
    {
        $desk = Shifts::desk();
        $cloakroom = Shifts::cloakroom();

        self::assertTrue($desk->isOpenAt(Shifts::at('08:59:59')));
        self::assertFalse($desk->isOpenAt(Shifts::at('09:00:00')));
        self::assertTrue($cloakroom->isOpenAt(Shifts::at('19:59:59')));
        self::assertFalse($cloakroom->isOpenAt(Shifts::at('20:00:00')));
        self::assertFalse($desk->isOverAt(Shifts::at('11:59:59')));
        self::assertTrue($desk->isOverAt(Shifts::at('12:00:00')));
    }

    public function testTheRoleAShiftRequires(): void
    {
        self::assertTrue(Shifts::desk()->admits([]));
        self::assertFalse(Shifts::desk(role: 'FIRST_AID')->admits(['CASHIER']));
        self::assertTrue(Shifts::desk(role: 'FIRST_AID')->admits(['CASHIER', 'FIRST_AID']));
    }
}
