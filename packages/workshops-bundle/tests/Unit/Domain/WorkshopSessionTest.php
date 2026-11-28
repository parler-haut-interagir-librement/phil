<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Interval;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Domain\Status\SessionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkshopSession::class)]
#[CoversClass(RefusalReason::class)]
final class WorkshopSessionTest extends TestCase
{
    private static function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    private function session(int $capacity = 8, string $zone = 'America/Toronto'): WorkshopSession
    {
        return WorkshopSession::schedule(Workshop::create('Atelier', self::at('2026-11-01')), new Interval(self::at('2026-11-10 14:00'), self::at('2026-11-10 17:00')), $zone, $capacity);
    }

    public function testASessionIsScheduledOnItsOwnClock(): void
    {
        $workshop = Workshop::create('Atelier', self::at('2026-11-01'));
        $session = WorkshopSession::schedule($workshop, new Interval(self::at('2026-11-10 14:00'), self::at('2026-11-10 17:00')), 'America/Toronto', 8);

        self::assertSame($workshop, $session->workshop());
        self::assertSame('2026-11-10T09:00:00-05:00', $session->span()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-10T12:00:00-05:00', $session->span()->end->format(\DATE_ATOM));
        self::assertSame('America/Toronto', $session->timezone()->getName());
        self::assertSame(8, $session->capacity());
        self::assertSame(SessionStatus::Scheduled, $session->status());
        self::assertNull($session->opensAt());
        self::assertNull($session->closesAt());
        self::assertSame('2026-11-10T09:00:00-05:00', $session->closing()->format(\DATE_ATOM));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7/', $session->id);
    }

    public function testAnInactiveWorkshopTakesNoSession(): void
    {
        $workshop = Workshop::create('Atelier', self::at('2026-11-01'));
        $workshop->activate(false);

        $this->expectExceptionObject(InvalidWorkshop::because('An inactive workshop takes no new session.'));

        WorkshopSession::schedule($workshop, new Interval(self::at('2026-11-10 14:00'), self::at('2026-11-10 17:00')), 'UTC', 8);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badZones(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['Mars/Olympus'];
        yield 'too long' => [str_repeat('A', 65)];
        yield 'a null byte' => ["Europe/\0Paris"];
        yield 'multibyte' => [str_repeat('é', 70)];
    }

    #[DataProvider('badZones')]
    public function testAnUnknownZoneIsRefused(string $zone): void
    {
        try {
            $this->session(8, $zone);
            self::fail('Not a time zone.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame(\sprintf('"%s" is not a time zone.', mb_substr($zone, 0, 64)), $invalidWorkshop->getMessage());
            self::assertLessThanOrEqual(64, mb_strlen(mb_substr($zone, 0, 64)));
        }
    }

    public function testTheCapacityIsBounded(): void
    {
        self::assertSame(1, $this->session(1)->capacity());
        self::assertSame(100_000, $this->session(100_000)->capacity());

        foreach ([0, -1, 100_001] as $capacity) {
            try {
                $this->session($capacity);
                self::fail('The capacity is refused.');
            } catch (InvalidWorkshop $exception) {
                self::assertSame('A session holds from 1 to 100000 seats.', $exception->getMessage());
            }
        }
    }

    public function testRegistrationIsOpenUntilTheSessionStartsWhenItHasNoWindow(): void
    {
        $session = $this->session();

        self::assertNull($session->refusalAt(self::at('2026-01-01')));
        self::assertNull($session->refusalAt(self::at('2026-11-10 13:59:59')));
        self::assertSame(RefusalReason::Closed, $session->refusalAt(self::at('2026-11-10 14:00')));
    }

    public function testTheWindowOpensAndClosesRegistration(): void
    {
        $session = $this->session();
        $session->openRegistration(self::at('2026-11-01 12:00'), self::at('2026-11-09 12:00'));

        self::assertSame('2026-11-01T07:00:00-05:00', $session->opensAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-09T07:00:00-05:00', $session->closesAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-09T07:00:00-05:00', $session->closing()->format(\DATE_ATOM));
        self::assertSame(RefusalReason::NotOpen, $session->refusalAt(self::at('2026-11-01 11:59:59')));
        self::assertNull($session->refusalAt(self::at('2026-11-01 12:00')));
        self::assertNull($session->refusalAt(self::at('2026-11-09 11:59:59')));
        self::assertSame(RefusalReason::Closed, $session->refusalAt(self::at('2026-11-09 12:00')));

        $session->openRegistration(null, null);
        self::assertNull($session->opensAt());
        self::assertNull($session->refusalAt(self::at('2026-11-10 13:00')));
    }

    public function testRegistrationMayCloseAsLateAsTheEnd(): void
    {
        $session = $this->session();
        $session->openRegistration(null, self::at('2026-11-10 17:00'));

        self::assertNull($session->refusalAt(self::at('2026-11-10 16:59:59')));

        $this->expectExceptionObject(InvalidWorkshop::because('Registration closes at the latest when the session ends.'));
        $session->openRegistration(null, self::at('2026-11-10 17:00:01'));
    }

    public function testRegistrationOpensBeforeItCloses(): void
    {
        $session = $this->session();

        foreach ([[self::at('2026-11-09'), self::at('2026-11-09')], [self::at('2026-11-10 14:00'), null], [self::at('2026-11-10 15:00'), null]] as [$opens, $closes]) {
            try {
                $session->openRegistration($opens, $closes);
                self::fail('The window is refused.');
            } catch (InvalidWorkshop $exception) {
                self::assertSame('Registration opens before it closes.', $exception->getMessage());
            }
        }

        $session->openRegistration(self::at('2026-11-10 13:59:59'), null);
        self::assertSame('2026-11-10T08:59:59-05:00', $session->opensAt()?->format(\DATE_ATOM));
    }

    public function testACancelledSessionRefusesEverything(): void
    {
        $session = $this->session();
        $session->openRegistration(self::at('2026-11-05'), null);
        $session->cancel();

        self::assertSame(SessionStatus::Cancelled, $session->status());
        self::assertSame(RefusalReason::Cancelled, $session->refusalAt(self::at('2026-11-01')));
        self::assertSame(RefusalReason::Cancelled, $session->refusalAt(self::at('2026-11-11')));

        foreach (['cancel' => $session->cancel(...), 'resize' => static fn () => $session->resize(4), 'reschedule' => static fn () => $session->reschedule(new Interval(self::at('2026-11-11 14:00'), self::at('2026-11-11 17:00')))] as $change => $call) {
            try {
                $call();
                self::fail($change.' is refused.');
            } catch (InvalidWorkshop $exception) {
                self::assertSame(\sprintf('A cancelled session is not %s.', ['cancel' => 'cancelled', 'resize' => 'resized', 'reschedule' => 'moved'][$change]), $exception->getMessage());
            }
        }

        self::assertSame(8, $session->capacity());
    }

    public function testASessionIsResized(): void
    {
        $session = $this->session();
        $session->resize(12);

        self::assertSame(12, $session->capacity());

        $this->expectException(InvalidWorkshop::class);
        $session->resize(0);
    }

    public function testASessionIsMovedWithItsWindow(): void
    {
        $session = $this->session();
        $session->openRegistration(self::at('2026-11-01'), self::at('2026-11-10 15:00'));
        $session->reschedule(new Interval(self::at('2026-11-12 14:00'), self::at('2026-11-12 17:00')));

        self::assertSame('2026-11-12T09:00:00-05:00', $session->span()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-12T12:00:00-05:00', $session->span()->end->format(\DATE_ATOM));

        try {
            $session->reschedule(new Interval(self::at('2026-11-10 11:00'), self::at('2026-11-10 14:59')));
            self::fail('The window no longer fits.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame('Registration closes at the latest when the session ends.', $invalidWorkshop->getMessage());
        }

        self::assertSame('2026-11-12T09:00:00-05:00', $session->span()->start->format(\DATE_ATOM));
    }

    public function testMovingASessionBeforeItsOpeningIsRefused(): void
    {
        $session = $this->session();
        $session->openRegistration(self::at('2026-11-05'), null);

        $this->expectExceptionObject(InvalidWorkshop::because('Registration opens before it closes.'));
        $session->reschedule(new Interval(self::at('2026-11-04 14:00'), self::at('2026-11-04 17:00')));
    }
}
