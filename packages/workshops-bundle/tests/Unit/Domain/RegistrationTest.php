<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Interval;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Registration::class)]
#[CoversClass(RegistrationStatus::class)]
final class RegistrationTest extends TestCase
{
    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    private function session(): WorkshopSession
    {
        return WorkshopSession::schedule(Workshop::create('Atelier', $this->at('2026-11-01')), new Interval($this->at('2026-11-10 14:00'), $this->at('2026-11-10 17:00')), 'America/Toronto', 8);
    }

    public function testAConfirmedRegistrationTakesItsSeats(): void
    {
        $session = $this->session();
        $registration = Registration::confirmed($session, 'account-1', 2, $this->at('2026-11-01 12:00'));

        self::assertSame($session, $registration->session());
        self::assertSame(['account-1', 2, RegistrationStatus::Confirmed], [$registration->participant(), $registration->seats(), $registration->status()]);
        self::assertSame('2026-11-01T07:00:00-05:00', $registration->createdAt()->format(\DATE_ATOM));
        self::assertNull($registration->cancelledAt());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7/', $registration->id);
    }

    public function testARegistrationIsCancelledOnce(): void
    {
        $registration = Registration::confirmed($this->session(), 'a', 1, $this->at('2026-11-01'));
        $registration->cancel($this->at('2026-11-02 12:00'));

        self::assertSame(RegistrationStatus::Cancelled, $registration->status());
        self::assertSame('2026-11-02T07:00:00-05:00', $registration->cancelledAt()?->format(\DATE_ATOM));

        try {
            $registration->cancel($this->at('2026-11-03'));
            self::fail('A cancelled registration is not cancelled again.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame('A cancelled registration is not cancelled again.', $invalidWorkshop->getMessage());
            self::assertSame('2026-11-02T07:00:00-05:00', $registration->cancelledAt()->format(\DATE_ATOM));
        }
    }

    public function testTheStatusesThatCount(): void
    {
        self::assertTrue(RegistrationStatus::Confirmed->isLive());
        self::assertFalse(RegistrationStatus::Cancelled->isLive());
    }

    public function testAParticipantOfTheLongestLengthIsTaken(): void
    {
        self::assertSame(255, \strlen(Registration::confirmed($this->session(), str_repeat('a', 255), 1, $this->at('2026-11-01'))->participant()));
        self::assertSame('a@b', Registration::confirmed($this->session(), 'a@b', 1, $this->at('2026-11-01'))->participant());
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function badRegistrations(): iterable
    {
        yield 'no participant' => ['', 1, 'A participant is an identifier of 1 to 255 bytes.'];
        yield 'a participant too long' => [str_repeat('a', 256), 1, 'A participant is an identifier of 1 to 255 bytes.'];
        yield 'an e-mail address' => ['ada@example.org', 1, 'A participant is a stable identifier, never an e-mail address.'];
        yield 'an e-mail address with a subdomain' => ['ada@mail.example.org', 1, 'A participant is a stable identifier, never an e-mail address.'];
        yield 'no seat' => ['account-1', 0, 'A registration takes at least one seat.'];
        yield 'seats below zero' => ['account-1', -1, 'A registration takes at least one seat.'];
    }

    #[DataProvider('badRegistrations')]
    public function testABadRegistrationIsRefused(string $participant, int $seats, string $message): void
    {
        $this->expectExceptionObject(InvalidWorkshop::because($message));

        Registration::confirmed($this->session(), $participant, $seats, $this->at('2026-11-01'));
    }
}
