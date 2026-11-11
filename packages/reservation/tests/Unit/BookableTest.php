<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Interval;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;
use Phil\Reservation\Violation;
use Phil\Reservation\ViolationCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bookable::class)]
final class BookableTest extends TestCase
{
    private function zone(): DateTimeZone
    {
        return new DateTimeZone('America/Toronto');
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, $this->zone());
    }

    private function span(string $start, string $end): Interval
    {
        return new Interval($this->at($start), $this->at($end));
    }

    private function request(string $start, string $end, int $quantity = 1, ?string $replacing = null): BookingRequest
    {
        return new BookingRequest($this->span($start, $end), $this->at('2026-11-01 12:00'), $quantity, $replacing);
    }

    /**
     * @param list<Interval> $slots
     *
     * @return list<string>
     */
    private function written(array $slots): array
    {
        return array_map(static fn (Interval $slot): string => $slot->start->format('m-d H:i').'–'.$slot->end->format('H:i'), $slots);
    }

    public function testItKeepsItsDefinition(): void
    {
        $bookable = new Bookable();

        self::assertSame(1, $bookable->capacity);
        self::assertSame(0, $bookable->bufferBefore);
        self::assertSame(0, $bookable->bufferAfter);
        self::assertSame([], $bookable->rules);
        self::assertSame('UTC', $bookable->timezone->getName());
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function badDefinitions(): iterable
    {
        yield 'no capacity' => [0, 0, 0, 'A resource holds at least one unit, not 0.'];
        yield 'negative buffer before' => [1, -1, 0, 'The buffers of a resource are not negative.'];
        yield 'negative buffer after' => [1, 0, -1, 'The buffers of a resource are not negative.'];
    }

    #[DataProvider('badDefinitions')]
    public function testItsDefinitionIsChecked(int $capacity, int $before, int $after, string $message): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Bookable($capacity, $before, $after);
    }

    public function testAFreeResourceAcceptsARequest(): void
    {
        $decision = new Bookable(timezone: $this->zone())->check($this->request('2026-11-10 09:00', '2026-11-10 10:00'), []);

        self::assertTrue($decision->allowed());
        self::assertSame([], $decision->conflicts);
    }

    public function testABookedResourceRefusesARequestThatMeetsABooking(): void
    {
        $bookable = new Bookable(timezone: $this->zone());
        $bookings = [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00')), new Booking('b', $this->span('2026-11-10 12:00', '2026-11-10 13:00'))];

        $refused = $bookable->check($this->request('2026-11-10 09:30', '2026-11-10 10:30'), $bookings);
        self::assertFalse($refused->allowed());
        self::assertTrue($refused->has(ViolationCode::Full));
        self::assertSame(['free' => 0], $refused->violations[0]->parameters);
        self::assertSame(['a'], $refused->conflicts);

        $touching = $bookable->check($this->request('2026-11-10 10:00', '2026-11-10 12:00'), $bookings);
        self::assertTrue($touching->allowed());
        self::assertSame([], $touching->conflicts);
    }

    public function testBuffersAddUp(): void
    {
        $bookable = new Bookable(bufferBefore: 15, bufferAfter: 15, timezone: $this->zone());
        $bookings = [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00'))];

        self::assertTrue($bookable->check($this->request('2026-11-10 10:29', '2026-11-10 11:00'), $bookings)->has(ViolationCode::Full));
        self::assertTrue($bookable->check($this->request('2026-11-10 10:30', '2026-11-10 11:00'), $bookings)->allowed());
        self::assertTrue($bookable->check($this->request('2026-11-10 08:00', '2026-11-10 08:31'), $bookings)->has(ViolationCode::Full));
        self::assertTrue($bookable->check($this->request('2026-11-10 08:00', '2026-11-10 08:30'), $bookings)->allowed());
        self::assertSame(['a'], $bookable->check($this->request('2026-11-10 10:29', '2026-11-10 11:00'), $bookings)->conflicts);
    }

    public function testABufferOnOneSideOnly(): void
    {
        $after = new Bookable(bufferAfter: 30);
        $before = new Bookable(bufferBefore: 30);
        $bookings = [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00'))];

        self::assertTrue($after->check($this->request('2026-11-10 10:29', '2026-11-10 11:00'), $bookings)->has(ViolationCode::Full));
        self::assertTrue($after->check($this->request('2026-11-10 10:30', '2026-11-10 11:00'), $bookings)->allowed());
        self::assertTrue($before->check($this->request('2026-11-10 10:29', '2026-11-10 11:00'), $bookings)->has(ViolationCode::Full));
        self::assertTrue($before->check($this->request('2026-11-10 10:30', '2026-11-10 11:00'), $bookings)->allowed());
    }

    public function testCapacityCountsTheUnitsHeldAtOnce(): void
    {
        $table = new Bookable(8);
        $bookings = [
            new Booking('a', $this->span('2026-11-10 18:00', '2026-11-10 20:00'), 3),
            new Booking('b', $this->span('2026-11-10 19:00', '2026-11-10 21:00'), 4),
            new Booking('c', $this->span('2026-11-10 20:00', '2026-11-10 22:00'), 2),
        ];

        // From 19:00 to 20:00, 7 seats are taken; from 20:00, 6.
        self::assertTrue($table->check($this->request('2026-11-10 18:00', '2026-11-10 22:00'), $bookings)->allowed());
        $full = $table->check($this->request('2026-11-10 18:00', '2026-11-10 22:00', 2), $bookings);
        self::assertSame(['free' => 1], $full->violations[0]->parameters);
        self::assertSame(['a', 'b', 'c'], $full->conflicts);
        self::assertTrue($table->check($this->request('2026-11-10 20:00', '2026-11-10 22:00', 2), $bookings)->allowed());
        self::assertSame(1, $table->free($this->span('2026-11-10 18:00', '2026-11-10 22:00'), $bookings));
        self::assertSame(2, $table->free($this->span('2026-11-10 20:00', '2026-11-10 22:00'), $bookings));
        self::assertSame(8, $table->free($this->span('2026-11-10 22:00', '2026-11-10 23:00'), $bookings));
        self::assertSame(5, $table->free($this->span('2026-11-10 18:00', '2026-11-10 22:00'), $bookings, 'b'));
    }

    public function testBuffersAreCountedToTheSecond(): void
    {
        $before = new Bookable(bufferBefore: 1);
        $after = new Bookable(bufferAfter: 1);
        $earlier = [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00:01'))];
        $later = [new Booking('a', $this->span('2026-11-10 11:00', '2026-11-10 12:00'))];

        // The request's buffer before meets the end of the booking, and the booking's buffer before meets the request's end.
        self::assertTrue($before->check($this->request('2026-11-10 10:01:00', '2026-11-10 10:30'), $earlier)->has(ViolationCode::Full));
        self::assertTrue($before->check($this->request('2026-11-10 10:01:01', '2026-11-10 10:30'), $earlier)->allowed());
        self::assertTrue($before->check($this->request('2026-11-10 10:30', '2026-11-10 10:59:01'), $later)->has(ViolationCode::Full));
        self::assertTrue($before->check($this->request('2026-11-10 10:30', '2026-11-10 10:59:00'), $later)->allowed());
        self::assertTrue($after->check($this->request('2026-11-10 10:30', '2026-11-10 10:59:01'), $later)->has(ViolationCode::Full));
        self::assertTrue($after->check($this->request('2026-11-10 10:30', '2026-11-10 10:59:00'), $later)->allowed());
        self::assertTrue($after->check($this->request('2026-11-10 10:01:00', '2026-11-10 10:30'), $earlier)->has(ViolationCode::Full));
        self::assertTrue($after->check($this->request('2026-11-10 10:01:01', '2026-11-10 10:30'), $earlier)->allowed());
        self::assertSame(0, $before->free($this->span('2026-11-10 10:01:00', '2026-11-10 10:30'), $earlier));
        self::assertSame(1, $before->free($this->span('2026-11-10 10:01:01', '2026-11-10 10:30'), $earlier));
        self::assertSame(0, $after->free($this->span('2026-11-10 10:30', '2026-11-10 10:59:01'), $later));
        self::assertSame(1, $after->free($this->span('2026-11-10 10:30', '2026-11-10 10:59:00'), $later));
        self::assertSame(0, $before->free($this->span('2026-11-10 10:30', '2026-11-10 10:59:01'), $later));
        self::assertSame(0, $after->free($this->span('2026-11-10 10:01:00', '2026-11-10 10:30'), $earlier));
    }

    public function testFreeUnitsCountTheBuffers(): void
    {
        $room = new Bookable(bufferBefore: 10, bufferAfter: 20);
        $bookings = [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00'))];

        self::assertSame(0, $room->free($this->span('2026-11-10 10:00', '2026-11-10 10:29'), $bookings));
        self::assertSame(1, $room->free($this->span('2026-11-10 10:30', '2026-11-10 11:00'), $bookings));
        self::assertSame(0, $room->free($this->span('2026-11-10 10:21', '2026-11-10 11:00'), $bookings));
        self::assertSame(1, $room->free($this->span('2026-11-10 10:30', '2026-11-10 11:00'), $bookings));
        self::assertSame(0, $room->free($this->span('2026-11-10 08:00', '2026-11-10 08:31'), $bookings));
        self::assertSame(1, $room->free($this->span('2026-11-10 08:00', '2026-11-10 08:30'), $bookings));
    }

    public function testFreeUnitsAreNeverNegative(): void
    {
        $bookings = [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 2)];

        self::assertSame(0, new Bookable()->free($this->span('2026-11-10 09:00', '2026-11-10 10:00'), $bookings));
        self::assertSame(['free' => 0], new Bookable()->check($this->request('2026-11-10 09:00', '2026-11-10 10:00'), $bookings)->violations[0]->parameters);
    }

    public function testTheBookingBeingMovedIsLeftOut(): void
    {
        $bookable = new Bookable();
        $bookings = [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00'))];

        $moved = $bookable->check($this->request('2026-11-10 09:30', '2026-11-10 10:30', 1, 'a'), $bookings);
        self::assertTrue($moved->allowed());
        self::assertSame([], $moved->conflicts);
        self::assertFalse($bookable->check($this->request('2026-11-10 09:30', '2026-11-10 10:30', 1, 'z'), $bookings)->allowed());
    }

    public function testTheQuantityIsBetweenOneAndTheCapacity(): void
    {
        $table = new Bookable(4);

        foreach ([0, 5] as $quantity) {
            $decision = $table->check($this->request('2026-11-10 09:00', '2026-11-10 10:00', $quantity), []);
            self::assertCount(1, $decision->violations);
            self::assertSame(ViolationCode::Quantity, $decision->violations[0]->code);
            self::assertSame(['max' => 4], $decision->violations[0]->parameters);
        }

        self::assertTrue($table->check($this->request('2026-11-10 09:00', '2026-11-10 10:00', 4), [])->allowed());
        self::assertTrue($table->check($this->request('2026-11-10 09:00', '2026-11-10 10:00', 1), [])->allowed());
    }

    public function testRulesAndHoursGiveTheirReasons(): void
    {
        $bookable = new Bookable(
            availability: new WeeklyAvailability([2 => [[540, 1020]]], $this->zone()),
            rules: [new MinimumNotice(60 * 24 * 30), new SlotGrid(30)],
            timezone: $this->zone(),
        );

        $decision = $bookable->check($this->request('2026-11-10 16:45', '2026-11-10 17:15'), [new Booking('a', $this->span('2026-11-10 16:00', '2026-11-10 17:00'))]);
        self::assertSame([ViolationCode::TooSoon, ViolationCode::OffGrid, ViolationCode::Unavailable, ViolationCode::Full], array_map(static fn (Violation $violation): ViolationCode => $violation->code, $decision->violations));
        self::assertTrue($bookable->check($this->request('2026-12-08 09:00', '2026-12-08 17:00'), [])->allowed());
        self::assertTrue($bookable->open($this->span('2026-12-01 09:00', '2026-12-01 17:00')));
        self::assertFalse($bookable->open($this->span('2026-12-01 08:59', '2026-12-01 17:00')));
        self::assertFalse($bookable->open($this->span('2026-12-02 09:00', '2026-12-02 10:00')));
    }

    public function testItListsTheFreeSlotsOfAPeriod(): void
    {
        $room = new Bookable(
            availability: new WeeklyAvailability([2 => [[540, 720], [780, 1020]]], $this->zone()),
            timezone: $this->zone(),
        );
        $bookings = [new Booking('a', $this->span('2026-11-10 10:00', '2026-11-10 11:00')), new Booking('far', $this->span('2026-11-20 10:00', '2026-11-20 11:00'))];

        self::assertSame(
            ['11-10 09:00–10:00', '11-10 11:00–12:00', '11-10 13:00–14:00', '11-10 14:00–15:00', '11-10 15:00–16:00', '11-10 16:00–17:00'],
            $this->written($room->slots($this->span('2026-11-10 00:00', '2026-11-11 00:00'), 60, $bookings, $this->at('2026-11-01 00:00'))),
        );
        self::assertSame(
            ['11-10 09:00–10:00', '11-10 11:00–12:00', '11-10 13:00–14:00'],
            $this->written($room->slots($this->span('2026-11-10 00:00', '2026-11-11 00:00'), 60, $bookings, $this->at('2026-11-01 00:00'), limit: 3)),
        );
        self::assertSame($this->zone()->getName(), $room->slots($this->span('2026-11-10 00:00', '2026-11-11 00:00'), 60, [], $this->at('2026-11-01 00:00'))[0]->start->getTimezone()->getName());
    }

    public function testSlotsGoByTheStepFromMidnight(): void
    {
        $room = new Bookable(timezone: $this->zone());

        self::assertSame(
            ['11-10 09:30–10:30', '11-10 10:00–11:00', '11-10 10:30–11:30'],
            $this->written($room->slots($this->span('2026-11-10 09:10', '2026-11-10 11:40'), 60, [], $this->at('2026-11-01 00:00'), step: 30)),
        );
        self::assertSame(
            ['11-10 09:30–10:30'],
            $this->written($room->slots($this->span('2026-11-10 09:30', '2026-11-10 10:30'), 60, [], $this->at('2026-11-01 00:00'), step: 30)),
        );
        self::assertSame(
            ['11-10 09:15–09:30', '11-10 09:30–09:45'],
            $this->written($room->slots($this->span('2026-11-10 09:01', '2026-11-10 09:45'), 15, [], $this->at('2026-11-01 00:00'))),
        );
    }

    public function testSlotsStartAnewEachDay(): void
    {
        $room = new Bookable(timezone: $this->zone());

        // A step of 7 hours: 0:00, 7:00, 14:00, 21:00, then midnight again.
        self::assertSame(
            ['11-10 21:00–22:00', '11-11 00:00–01:00', '11-11 07:00–08:00'],
            $this->written($room->slots($this->span('2026-11-10 20:00', '2026-11-11 08:00'), 60, [], $this->at('2026-11-01 00:00'), step: 420)),
        );
    }

    public function testSlotsKeepTheBuffersAndTheQuantity(): void
    {
        $table = new Bookable(4, 0, 30, timezone: $this->zone());
        $bookings = [new Booking('a', $this->span('2026-11-10 10:00', '2026-11-10 11:00'), 3)];
        $period = $this->span('2026-11-10 09:00', '2026-11-10 13:00');
        $now = $this->at('2026-11-01 00:00');

        self::assertSame(['11-10 09:00–10:00', '11-10 10:00–11:00', '11-10 11:00–12:00', '11-10 12:00–13:00'], $this->written($table->slots($period, 60, $bookings, $now)));
        self::assertSame(['11-10 11:30–12:30', '11-10 12:00–13:00'], $this->written($table->slots($period, 60, $bookings, $now, 2, 30)));
        self::assertSame([], $table->slots($period, 60, [], $now, 5));
        self::assertSame([], $table->slots($period, 60, [], $now, 0));
        self::assertCount(4, $table->slots($period, 60, [], $now, 4));
    }

    public function testSlotBuffersAreCountedToTheSecond(): void
    {
        $before = new Bookable(bufferBefore: 1, timezone: $this->zone());
        $after = new Bookable(bufferAfter: 1, timezone: $this->zone());
        $now = $this->at('2026-11-01 00:00');
        $period = $this->span('2026-11-10 10:01', '2026-11-10 10:02');

        // A slot from 10:01 to 10:02 holds the room from 10:00 with a buffer before, until 10:03 with one after.
        self::assertSame([], $before->slots($period, 1, [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00:01'))], $now));
        self::assertCount(1, $before->slots($period, 1, [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00'))], $now));
        self::assertSame([], $before->slots($period, 1, [new Booking('a', $this->span('2026-11-10 09:00', '2026-11-10 10:00:30'))], $now));
        self::assertSame([], $after->slots($period, 1, [new Booking('a', $this->span('2026-11-10 10:02:59', '2026-11-10 11:00'))], $now));
        self::assertCount(1, $after->slots($period, 1, [new Booking('a', $this->span('2026-11-10 10:03', '2026-11-10 11:00'))], $now));
        self::assertSame([], $after->slots($period, 1, [new Booking('a', $this->span('2026-11-10 10:02:30', '2026-11-10 11:00'))], $now));
        self::assertSame([], $before->slots($period, 1, [new Booking('a', $this->span('2026-11-10 10:01:59', '2026-11-10 11:00'))], $now));
    }

    public function testASlotEndsWithinThePeriod(): void
    {
        $room = new Bookable(timezone: $this->zone());

        self::assertSame([], $room->slots($this->span('2026-11-10 09:00', '2026-11-10 09:59:30'), 60, [], $this->at('2026-11-01 00:00')));
        self::assertSame(['11-10 09:30–10:30'], $this->written($room->slots($this->span('2026-11-10 09:00:01', '2026-11-10 10:30'), 60, [], $this->at('2026-11-01 00:00'), step: 30)));
    }

    public function testSlotsAreAHundredByDefault(): void
    {
        self::assertCount(100, new Bookable()->slots($this->span('2026-11-10 00:00', '2026-11-11 00:00'), 1, [], $this->at('2026-11-01 00:00')));
    }

    public function testTheWalkReturnsTheSlotsFoundBeforeItsBound(): void
    {
        $room = new Bookable(timezone: $this->zone());
        $taken = [new Booking('a', $this->span('2026-11-10 00:02', '2026-11-30 00:00'))];

        self::assertSame(['11-10 00:00–00:01', '11-10 00:01–00:02'], $this->written($room->slots($this->span('2026-11-10 00:00', '2026-11-30 00:00'), 1, $taken, $this->at('2026-11-01 00:00'))));
    }

    public function testSlotsFollowTheRules(): void
    {
        $room = new Bookable(rules: [new MinimumNotice(120), new DurationLimits(30, 90)], timezone: $this->zone());
        $period = $this->span('2026-11-10 09:00', '2026-11-10 13:00');

        self::assertSame(['11-10 12:00–13:00'], $this->written($room->slots($period, 60, [], $this->at('2026-11-10 10:00'))));
        self::assertSame([], $room->slots($period, 120, [], $this->at('2026-11-01 10:00')));
    }

    public function testSlotsAreBounded(): void
    {
        $room = new Bookable(timezone: $this->zone());
        $now = $this->at('2026-11-01 00:00');

        self::assertCount(Bookable::MAX_SLOTS, $room->slots($this->span('2026-11-10 00:00', '2026-11-20 00:00'), 1, [], $now, limit: Bookable::MAX_SLOTS));
        // Every minute taken: the walk stops after its candidates, with what it found.
        $taken = [new Booking('a', $this->span('2026-11-10 00:00', '2026-11-30 00:00'))];
        self::assertSame([], $room->slots($this->span('2026-11-10 00:00', '2026-11-30 00:00'), 1, $taken, $now));
        $late = [new Booking('a', $this->span('2026-11-10 00:00', '2026-11-16 22:39'))];
        self::assertSame(['11-16 22:39–22:40'], $this->written($room->slots($this->span('2026-11-10 00:00', '2026-11-30 00:00'), 1, $late, $now, limit: 1)));
        $later = [new Booking('a', $this->span('2026-11-10 00:00', '2026-11-16 22:40'))];
        self::assertSame([], $room->slots($this->span('2026-11-10 00:00', '2026-11-30 00:00'), 1, $later, $now, limit: 1));
    }

    /**
     * @return iterable<string, array{int, ?int, int}>
     */
    public static function badSlotArguments(): iterable
    {
        yield 'no length' => [0, null, 10];
        yield 'no step' => [60, 0, 10];
        yield 'no limit' => [60, null, 0];
        yield 'too many' => [60, null, 1001];
    }

    #[DataProvider('badSlotArguments')]
    public function testSlotArgumentsAreBounded(int $minutes, ?int $step, int $limit): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('A slot lasts a minute or more, its starts are a minute apart or more, and at most 1000 slots are read.');

        new Bookable()->slots($this->span('2026-11-10 09:00', '2026-11-10 13:00'), $minutes, [], $this->at('2026-11-01'), 1, $step, $limit);
    }
}
