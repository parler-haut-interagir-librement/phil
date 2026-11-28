<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\WaitlistBundle\Domain\Exception\InvalidWaitlist;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WaitlistBundle\Domain\Status\WaitlistMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Entry::class)]
#[CoversClass(EntryStatus::class)]
final class EntryTest extends TestCase
{
    private function at(string $moment = '2026-11-01 12:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }

    private function entry(int $places = 2): Entry
    {
        return Entry::join(Waitlist::open('session', 'S-1', WaitlistMode::Queue, 3_600, $this->at()), 'account-1', $places, new DateTimeImmutable('2026-11-01 08:00:00.123456 America/Toronto'));
    }

    private function offered(): Entry
    {
        $entry = $this->entry();
        $entry->offer($this->at('2026-11-01 13:00:00'), $this->at('2026-11-01 14:00:00'));

        return $entry;
    }

    public function testAnEntryWaitsRankedByTheMomentItJoinedInUtc(): void
    {
        $entry = $this->entry();

        self::assertSame(['account-1', 2, EntryStatus::Waiting], [$entry->participant(), $entry->places(), $entry->status()]);
        self::assertSame('q2026-11-01T13:00:00.123456|'.$entry->id, $entry->rank());
        self::assertEquals(new DateTimeImmutable('2026-11-01 08:00:00.123456 America/Toronto'), $entry->createdAt());
        self::assertSame('S-1', $entry->waitlist()->subjectId());
        self::assertNull($entry->offeredAt());
        self::assertNull($entry->offerExpiresAt());
        self::assertNull($entry->endedAt());
        self::assertFalse($entry->hasExpiredAt($this->at('2030-01-01')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidParticipants(): iterable
    {
        yield 'an e-mail address' => ['someone@example.com'];
        yield 'empty' => [''];
        yield 'a space' => ['account 1'];
        yield 'starting with a dash' => ['-account'];
        yield 'too long' => [str_repeat('a', 191)];
        yield 'a line feed at the end' => ["account-1\n"];
    }

    #[DataProvider('invalidParticipants')]
    public function testAParticipantIsAStableIdentifier(string $participant): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('A participant is a stable identifier of letters, digits, "_", ".", ":" and "-", 190 characters at most, never an e-mail address.');

        Entry::join(Waitlist::open('session', 'S-1', WaitlistMode::Queue, 3_600, $this->at()), $participant, 1, $this->at());
    }

    public function testTheLongestParticipantIsAccepted(): void
    {
        $participant = 'a'.str_repeat('Z9_.:-', 31).'abc';

        self::assertSame($participant, Entry::join(Waitlist::open('session', 'S-1', WaitlistMode::Queue, 3_600, $this->at()), $participant, 1, $this->at())->participant());
    }

    public function testAnEntryWaitsForOnePlaceAtLeast(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('An entry waits for at least one place.');

        $this->entry(0);
    }

    public function testADrawRanksAnEntryByTheSeed(): void
    {
        $entry = $this->entry();
        $entry->rankBy('seed-2026');

        self::assertSame('d'.hash('sha256', "seed-2026\n".$entry->id), $entry->rank());
        self::assertLessThan('q', $entry->rank());
    }

    public function testOnlyAWaitingEntryIsDrawn(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('Only a waiting entry is drawn, not an entry offered.');

        $this->offered()->rankBy('seed-2026');
    }

    public function testAnOfferIsHeldUntilItsDeadline(): void
    {
        $entry = $this->offered();

        self::assertSame(EntryStatus::Offered, $entry->status());
        self::assertEquals($this->at('2026-11-01 13:00:00'), $entry->offeredAt());
        self::assertEquals($this->at('2026-11-01 14:00:00'), $entry->offerExpiresAt());
        self::assertFalse($entry->hasExpiredAt($this->at('2026-11-01 13:59:59')));
        self::assertTrue($entry->hasExpiredAt($this->at('2026-11-01 14:00:00')));
    }

    public function testAnOfferLapsesAfterItIsMade(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('An offer lapses after it is made.');

        $this->entry()->offer($this->at(), $this->at());
    }

    public function testOnlyAWaitingEntryIsOffered(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('Only a waiting entry is offered places, not an entry offered.');

        $this->offered()->offer($this->at(), $this->at('2026-11-02'));
    }

    public function testAnOfferIsAcceptedBeforeItsDeadline(): void
    {
        $entry = $this->offered();
        $entry->accept($this->at('2026-11-01 13:59:59'));

        self::assertSame(EntryStatus::Accepted, $entry->status());
        self::assertEquals($this->at('2026-11-01 13:59:59'), $entry->endedAt());
        self::assertFalse($entry->status()->isLive());
        self::assertFalse($entry->hasExpiredAt($this->at('2026-11-02')));
    }

    public function testALapsedOfferIsNotAccepted(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('An offer is accepted before its deadline.');

        $this->offered()->accept($this->at('2026-11-01 14:00:00'));
    }

    public function testOnlyAnOfferIsAccepted(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('Only an offer is accepted, not an entry waiting.');

        $this->entry()->accept($this->at());
    }

    public function testAnOfferIsDeclined(): void
    {
        $entry = $this->offered();
        $entry->decline($this->at('2026-11-01 13:30:00'));

        self::assertSame(EntryStatus::Declined, $entry->status());
        self::assertEquals($this->at('2026-11-01 13:30:00'), $entry->endedAt());
    }

    public function testOnlyAnOfferIsDeclined(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('Only an offer is declined, not an entry waiting.');

        $this->entry()->decline($this->at());
    }

    public function testAnOfferLapsesAtItsDeadline(): void
    {
        $entry = $this->offered();
        $entry->lapse($this->at('2026-11-01 14:00:00'));

        self::assertSame(EntryStatus::Lapsed, $entry->status());
        self::assertEquals($this->at('2026-11-01 14:00:00'), $entry->endedAt());
    }

    public function testAnOfferDoesNotLapseBeforeItsDeadline(): void
    {
        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('Only an offer whose deadline has come lapses.');

        $this->offered()->lapse($this->at('2026-11-01 13:59:59'));
    }

    public function testAWaitingEntryDoesNotLapse(): void
    {
        $this->expectException(InvalidWaitlist::class);

        $this->entry()->lapse($this->at('2030-01-01'));
    }

    public function testAWaitingOrOfferedEntryLeaves(): void
    {
        $waiting = $this->entry();
        $waiting->leave($this->at());

        $offered = $this->offered();
        $offered->leave($this->at('2026-11-01 13:10:00'));

        self::assertSame([EntryStatus::Left, EntryStatus::Left], [$waiting->status(), $offered->status()]);
        self::assertEquals($this->at('2026-11-01 13:10:00'), $offered->endedAt());
    }

    public function testAnEntryLeavesOnce(): void
    {
        $entry = $this->entry();
        $entry->leave($this->at());

        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('An entry left has left the list already.');

        $entry->leave($this->at());
    }

    public function testTheLiveStatuses(): void
    {
        self::assertSame(
            [true, true, false, false, false, false],
            array_map(static fn (EntryStatus $status): bool => $status->isLive(), EntryStatus::cases()),
        );
    }
}
