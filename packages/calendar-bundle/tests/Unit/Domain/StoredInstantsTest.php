<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Tests\Unit\Fixtures\StoredDatabase;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The instants an event and an attendance keep, written and read back in UTC while PHP runs in another time zone.
 */
#[CoversClass(Event::class)]
#[CoversClass(Attendance::class)]
final class StoredInstantsTest extends TestCase
{
    private string $defaultZone;

    #[Override]
    protected function setUp(): void
    {
        $this->defaultZone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
    }

    #[Override]
    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultZone);
    }

    public function testAnEventReadBackKeepsItsInstantsAndItsTimeZone(): void
    {
        $manager = StoredDatabase::entityManager();
        $zone = new DateTimeZone('America/Toronto');
        $event = Event::schedule('Soirée jeux', new DateTimeImmutable('2026-11-10 19:00', $zone), new DateTimeImmutable('2026-11-10 22:00', $zone));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));

        $attendance = new Attendance($event, null, Member::of('member', '7'), new DateTimeImmutable('2026-11-01 08:30', new DateTimeZone('Europe/Paris')));
        $manager->persist($event);
        $manager->persist($attendance);
        $manager->flush();
        $manager->clear();

        $stored = $manager->find(Event::class, $event->id) ?? self::fail('Saved.');
        $attended = $manager->find(Attendance::class, $attendance->id) ?? self::fail('Saved.');

        self::assertSame('2026-11-10 19:00 America/Toronto', $stored->start()->format('Y-m-d H:i e'));
        self::assertSame('2026-11-10 22:00 America/Toronto', $stored->end()->format('Y-m-d H:i e'));
        self::assertSame('2026-11-25 03:00 UTC', $stored->seriesEndsAt()?->format('Y-m-d H:i e'));
        self::assertSame('2026-11-01 07:30 UTC', $attended->createdAt()->format('Y-m-d H:i e'));
        self::assertSame(['2026-11-11 00:00:00', '2026-11-11 03:00:00', '2026-11-25 03:00:00'], $manager->getConnection()->fetchNumeric('SELECT starts_at, ends_at, series_ends_at FROM phil_calendar_event'));
        self::assertSame('2026-11-01 07:30:00', $manager->getConnection()->fetchOne('SELECT created_at FROM phil_calendar_attendance'));
    }
}
