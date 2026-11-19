<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Availability\AlwaysAvailable;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Interval;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MaximumAdvance;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;
use Phil\ReservationBundle\Domain\Exception\InvalidReservation;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(BookableResource::class)]
#[CoversClass(InvalidReservation::class)]
final class BookableResourceTest extends TestCase
{
    public function testItIsCreatedWithItsDefaults(): void
    {
        $room = BookableResource::create('  Salle Verne  ');

        self::assertTrue(Uuid::isValid($room->id));
        self::assertSame('Salle Verne', $room->name());
        self::assertSame(1, $room->capacity());
        self::assertSame('UTC', $room->timezone()->getName());
        self::assertSame([0, 0], [$room->bufferBefore(), $room->bufferAfter()]);
        self::assertSame([null, null, null, null, null], [$room->minimumNotice(), $room->maximumAdvance(), $room->minimumDuration(), $room->maximumDuration(), $room->slotStep()]);
        self::assertNull($room->hours());
        self::assertSame([], $room->closedDays());
        self::assertSame([], $room->reminders());
        self::assertSame(15, $room->holdMinutes());
        self::assertTrue($room->isActive());
        self::assertSame(0, $room->claims());
        self::assertSame('America/Toronto', BookableResource::create('Table', 8, 'America/Toronto')->timezone()->getName());
        self::assertSame(8, BookableResource::create('Table', 8)->capacity());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => ['   '];
        yield 'too long' => [str_repeat('é', 201)];
    }

    #[DataProvider('badNames')]
    public function testItsNameIsChecked(string $name): void
    {
        $this->expectExceptionObject(InvalidReservation::because('The name of a resource has from 1 to 200 characters.'));

        BookableResource::create($name);
    }

    public function testANameOfTwoHundredCharactersIsKept(): void
    {
        $room = BookableResource::create(str_repeat('é', 200));
        $room->rename(' Salle Jules ');

        self::assertSame('Salle Jules', $room->name());
        $this->expectExceptionObject(InvalidReservation::because('The name of a resource has from 1 to 200 characters.'));

        $room->rename('');
    }

    public function testItsTimeZoneIsAnIanaIdentifier(): void
    {
        $room = BookableResource::create('Salle');
        $room->moveTo('Europe/Paris');
        self::assertSame('Europe/Paris', $room->timezone()->getName());

        // The identifier quoted is cut at 64 characters, not bytes.
        $this->expectExceptionObject(InvalidReservation::because('The time zone of a resource is an IANA identifier, not "'.str_repeat('é', 64).'".'));

        $room->moveTo(str_repeat('é', 70));
    }

    public function testAnOffsetIsNotATimeZone(): void
    {
        $this->expectExceptionObject(InvalidReservation::because('The time zone of a resource is an IANA identifier, not "+05:00".'));

        BookableResource::create('Salle', 1, '+05:00');
    }

    public function testItsCapacityIsAtLeastOne(): void
    {
        $room = BookableResource::create('Salle');
        $room->resize(12);
        self::assertSame(12, $room->capacity());

        try {
            $room->resize(0);
            self::fail('A capacity of zero is refused.');
        } catch (InvalidDefinition) {
            self::assertSame(12, $room->capacity());
        }

        $this->expectException(InvalidDefinition::class);

        BookableResource::create('Salle', 0);
    }

    public function testItsBuffersAreNotNegative(): void
    {
        $room = BookableResource::create('Salle');
        $room->buffer(10, 20);
        self::assertSame([10, 20], [$room->bufferBefore(), $room->bufferAfter()]);

        try {
            $room->buffer(5, -1);
            self::fail('A negative buffer is refused.');
        } catch (InvalidDefinition) {
            self::assertSame([10, 20], [$room->bufferBefore(), $room->bufferAfter()]);
        }
    }

    public function testItsRulesAreCheckedAndBuilt(): void
    {
        $room = BookableResource::create('Salle', 2, 'America/Toronto');
        $room->buffer(5, 10);
        $room->restrict(60, 43200, 30, 240, 30);

        self::assertSame([60, 43200, 30, 240, 30], [$room->minimumNotice(), $room->maximumAdvance(), $room->minimumDuration(), $room->maximumDuration(), $room->slotStep()]);
        $bookable = $room->bookable();
        self::assertSame([2, 5, 10, 'America/Toronto'], [$bookable->capacity, $bookable->bufferBefore, $bookable->bufferAfter, $bookable->timezone->getName()]);
        self::assertInstanceOf(AlwaysAvailable::class, $bookable->availability);
        self::assertEquals([new MinimumNotice(60), new MaximumAdvance(43200), new DurationLimits(30, 240), new SlotGrid(30)], $bookable->rules);

        $room->restrict(maximumDuration: 90);
        self::assertEquals([new DurationLimits(1, 90)], $room->bookable()->rules);
        $room->restrict(minimumDuration: 45);
        self::assertEquals([new DurationLimits(45)], $room->bookable()->rules);
        $room->restrict();
        self::assertSame([], $room->bookable()->rules);

        try {
            $room->restrict(slotStep: 7);
            self::fail('A step that does not divide a day is refused.');
        } catch (InvalidDefinition) {
            self::assertNull($room->slotStep());
        }

        foreach ([[-1, null, null, null], [null, 0, null, null], [null, null, 0, null], [null, null, 30, 20]] as [$notice, $advance, $minimum, $maximum]) {
            try {
                $room->restrict($notice, $advance, $minimum, $maximum);
                self::fail('The rule is refused.');
            } catch (InvalidDefinition) {
                self::assertSame([null, null, null, null], [$room->minimumNotice(), $room->maximumAdvance(), $room->minimumDuration(), $room->maximumDuration()]);
            }
        }
    }

    public function testItsHoursAreCheckedAndBuilt(): void
    {
        $room = BookableResource::create('Salle', 1, 'America/Toronto');
        $room->openOn([2 => [[780, 1020], [540, 720]]], ['2026-12-29', '2026-12-29', '2027-01-05']);

        self::assertSame([2 => [[540, 720], [780, 1020]]], $room->hours());
        self::assertSame(['2026-12-29', '2027-01-05'], $room->closedDays());
        $availability = $room->bookable()->availability;
        self::assertInstanceOf(WeeklyAvailability::class, $availability);
        self::assertSame('America/Toronto', $availability->timezone->getName());
        self::assertSame([2 => [[540, 720], [780, 1020]]], $availability->hours);
        $christmas = new Interval(new DateTimeImmutable('2026-12-22 00:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-12-30 00:00', new DateTimeZone('America/Toronto')));
        self::assertCount(2, $availability->windows($christmas));

        try {
            $room->openOn([9 => [[0, 60]]]);
            self::fail('A weekday that does not exist is refused.');
        } catch (InvalidDefinition) {
            self::assertSame([2 => [[540, 720], [780, 1020]]], $room->hours());
        }

        $room->openOn(null, ['2026-12-25']);
        self::assertNull($room->hours());
        self::assertSame([], $room->closedDays());
        self::assertInstanceOf(AlwaysAvailable::class, $room->bookable()->availability);
    }

    public function testItsRemindersAndHoldsAreBounded(): void
    {
        $room = BookableResource::create('Salle');
        $room->remindBefore(60, 1440, 60);
        self::assertSame([1440, 60], $room->reminders());
        self::assertSame([1440, 60], $room->reminderSchedule()->offsets);

        try {
            $room->remindBefore(0);
            self::fail('A reminder at the start is refused.');
        } catch (InvalidDefinition) {
            self::assertSame([1440, 60], $room->reminders());
        }

        $room->holdFor(1);
        self::assertSame(1, $room->holdMinutes());
        $room->holdFor(1440);
        self::assertSame(1440, $room->holdMinutes());

        foreach ([0, 1441] as $minutes) {
            try {
                $room->holdFor($minutes);
                self::fail('The hold is refused.');
            } catch (InvalidReservation $invalidReservation) {
                self::assertSame(\sprintf('A hold lasts from 1 to 1440 minutes, not %d.', $minutes), $invalidReservation->getMessage());
            }
        }
    }

    public function testItIsDeactivatedAndActivated(): void
    {
        $room = BookableResource::create('Salle');
        $room->activate(false);
        self::assertFalse($room->isActive());
        $room->activate();
        self::assertTrue($room->isActive());
    }

    public function testTheBookableDecidesWithItsRules(): void
    {
        $room = BookableResource::create('Salle', 1, 'America/Toronto');
        $room->restrict(minimumNotice: 60);

        $zone = new DateTimeZone('America/Toronto');
        $asked = new Interval(new DateTimeImmutable('2026-11-10 09:00', $zone), new DateTimeImmutable('2026-11-10 10:00', $zone));

        self::assertFalse($room->bookable()->check(new BookingRequest($asked, new DateTimeImmutable('2026-11-10 08:30', $zone)), [])->allowed());
        self::assertTrue($room->bookable()->check(new BookingRequest($asked, new DateTimeImmutable('2026-11-10 08:00', $zone)), [])->allowed());
    }
}
