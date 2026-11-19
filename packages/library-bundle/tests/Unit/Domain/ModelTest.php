<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\LibraryBundle\Domain\Event\CopyLent;
use Phil\LibraryBundle\Domain\Event\CopyReturned;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Phil\Reservation\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Title::class)]
#[CoversClass(Copy::class)]
#[CoversClass(Loan::class)]
#[CoversClass(CopyCondition::class)]
#[CoversClass(LoanStatus::class)]
#[CoversClass(CopyLent::class)]
#[CoversClass(CopyReturned::class)]
final class ModelTest extends TestCase
{
    public function testATitleKeepsItsRecord(): void
    {
        $record = new Record(TitleKind::Game, 'Azul', '9780306406157', 'Kiesling', 'Plan B', 2017, 2, 4, 30, 45, 8, 'en', 'Tiles.');
        $title = Title::catalogue($record, $this->at('2026-11-13 10:00'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $title->id);
        self::assertEquals($record, $title->record());
        self::assertSame(['Azul', TitleKind::Game, '2026-11-13T10:00:00-05:00'], [$title->name(), $title->kind(), $title->createdAt()->format(\DATE_ATOM)]);

        $title->describe(new Record(TitleKind::Book, 'Dune'));
        self::assertEquals(new Record(TitleKind::Book, 'Dune'), $title->record());
        self::assertSame(['Dune', TitleKind::Book], [$title->name(), $title->kind()]);
    }

    public function testACopyKeepsItsBarcodeConditionAndLink(): void
    {
        $copy = Copy::of($this->title(), 'G-0001', $this->at('2026-11-13 10:00'), CopyCondition::Worn, 'item-7');

        self::assertSame(['G-0001', CopyCondition::Worn, 'item-7', null, 0, true, '2026-11-13T10:00:00-05:00'], [$copy->barcode(), $copy->condition(), $copy->stockroomItem(), $copy->note(), $copy->claims(), $copy->mayLend(), $copy->createdAt()->format(\DATE_ATOM)]);
        self::assertSame('Azul', $copy->title()->name());
        $copy->link(null);
        self::assertNull($copy->stockroomItem());
        $copy->link('room:item.7_x-y');
        self::assertSame('room:item.7_x-y', $copy->stockroomItem());
        $copy->claim();
        $copy->claim();
        self::assertSame(2, $copy->claims());

        $copy->assess(CopyCondition::Incomplete, '  Two tiles missing. ');
        self::assertSame([CopyCondition::Incomplete, 'Two tiles missing.'], [$copy->condition(), $copy->note()]);
        $copy->assess(CopyCondition::Withdrawn, ' ');
        self::assertSame([CopyCondition::Withdrawn, null, false], [$copy->condition(), $copy->note(), $copy->mayLend()]);
        $copy->assess(CopyCondition::Good, str_repeat('é', 1000));
        self::assertSame(1000, mb_strlen((string) $copy->note()));
        $copy->assess(CopyCondition::Good, 'Boîte cassée, 5 € … ok');
        self::assertSame('Boîte cassée, 5 € … ok', $copy->note(), 'the bytes of a multibyte letter are no control characters');
        self::assertNull(Copy::of($this->title(), 'b', $this->at('2026-11-13'))->stockroomItem());
        self::assertSame(CopyCondition::Good, Copy::of($this->title(), 'b', $this->at('2026-11-13'))->condition());
    }

    public function testACopyRefusesBadValues(): void
    {
        foreach (['', 'a b', '-a', str_repeat('a', 65), "a\n"] as $barcode) {
            try {
                Copy::of($this->title(), $barcode, $this->at('2026-11-13'));
                self::fail(\sprintf('The barcode "%s" was taken.', $barcode));
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('A barcode is 1 to 64 letters, digits, dots, underscores or hyphens.', $invalid->getMessage());
            }
        }

        self::assertSame(str_repeat('a', 64), Copy::of($this->title(), str_repeat('a', 64), $this->at('2026-11-13'))->barcode());
        $copy = Copy::of($this->title(), 'b', $this->at('2026-11-13'));

        foreach (['a b', 'x@y.z', str_repeat('a', 129)] as $item) {
            try {
                $copy->link($item);
                self::fail('A bad item was linked.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('The item of a stockroom is named by an identifier of 1 to 128 letters, digits, dots, underscores, colons or hyphens.', $invalid->getMessage());
            }
        }

        foreach ([str_repeat('a', 1001), "a\x00b", "\xff"] as $note) {
            try {
                $copy->assess(CopyCondition::Worn, $note);
                self::fail('A bad note was kept.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('The note on a copy is a text of at most 1000 characters, without control characters.', $invalid->getMessage());
                self::assertSame(CopyCondition::Good, $copy->condition(), 'nothing changed');
            }
        }
    }

    public function testOnlyAWithdrawnCopyIsNotLent(): void
    {
        foreach (CopyCondition::cases() as $condition) {
            self::assertSame(CopyCondition::Withdrawn !== $condition, $condition->mayLend());
        }

        self::assertSame([LoanStatus::Reserved, LoanStatus::Active], LoanStatus::holding());
        self::assertSame([LoanStatus::Returned, LoanStatus::Cancelled, LoanStatus::Lost], LoanStatus::closed());

        foreach (LoanStatus::cases() as $status) {
            self::assertSame(\in_array($status, LoanStatus::holding(), true), $status->holdsCopy());
        }
    }

    public function testALoanLentNowRunsInUtc(): void
    {
        $copy = $this->copy();
        $loan = Loan::lend($copy, 'card-7', $this->at('2026-11-27 23:59:59'), $this->at('2026-11-13 10:00'));

        self::assertSame([LoanStatus::Active, 'card-7', 0, null, 0, null, null], [$loan->status(), $loan->member(), $loan->renewals(), $loan->lastReminder(), $loan->notices(), $loan->lastNoticeAt(), $loan->closedAt()]);
        self::assertSame($copy, $loan->copy());
        self::assertSame(['2026-11-13T15:00:00+00:00', '2026-11-28T04:59:59+00:00', '2026-11-13T15:00:00+00:00', '2026-11-13T15:00:00+00:00'], [$loan->startsAt()->format(\DATE_ATOM), $loan->dueAt()->format(\DATE_ATOM), $loan->checkedOutAt()?->format(\DATE_ATOM), $loan->createdAt()->format(\DATE_ATOM)]);
        self::assertSame([$loan->startsAt()->getTimestamp(), $loan->dueAt()->getTimestamp()], [$loan->span()->from, $loan->span()->until]);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $loan->id);
    }

    public function testALoanIsDueAfterItStartsAndForAMember(): void
    {
        foreach ([['card-7', '2026-11-13 10:00'], ['card-7', '2026-11-13 09:00']] as [$member, $due]) {
            try {
                Loan::lend($this->copy(), $member, $this->at($due), $this->at('2026-11-13 10:00'));
                self::fail('A loan was due when it started.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('A loan is due after it starts.', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidLibraryData::class);
        Loan::lend($this->copy(), 'jo@example.com', $this->at('2026-11-20'), $this->at('2026-11-13'));
    }

    public function testAReservationIsForASpanToCome(): void
    {
        $loan = Loan::reserve($this->copy(), 'card-7', $this->span('2026-11-20 10:00', '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));

        self::assertSame([LoanStatus::Reserved, null, '2026-11-20T15:00:00+00:00'], [$loan->status(), $loan->checkedOutAt(), $loan->startsAt()->format(\DATE_ATOM)]);

        foreach (['2026-11-13 10:00', '2026-11-13 09:00'] as $start) {
            try {
                Loan::reserve($this->copy(), 'card-7', $this->span($start, '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));
                self::fail('A reservation was made for a span begun.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('A reservation is for a span to come; a loan that starts now is lent.', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidLibraryData::class);
        Loan::reserve($this->copy(), 'a b', $this->span('2026-11-20 10:00', '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));
    }

    public function testAReservationIsCheckedOut(): void
    {
        $loan = Loan::reserve($this->copy(), 'card-7', $this->span('2026-11-20 10:00', '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));
        $loan->checkOut($this->at('2026-11-19 16:00'));

        self::assertSame([LoanStatus::Active, '2026-11-19T21:00:00+00:00', '2026-11-19T21:00:00+00:00'], [$loan->status(), $loan->startsAt()->format(\DATE_ATOM), $loan->checkedOutAt()?->format(\DATE_ATOM)], 'handed over early, it starts then');

        $late = Loan::reserve($this->copy(), 'card-7', $this->span('2026-11-20 10:00', '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));
        $late->checkOut($this->at('2026-11-21 09:00'));
        self::assertSame(['2026-11-20T15:00:00+00:00', '2026-11-21T14:00:00+00:00'], [$late->startsAt()->format(\DATE_ATOM), $late->checkedOutAt()?->format(\DATE_ATOM)], 'handed over late, it keeps its start');

        $same = Loan::reserve($this->copy(), 'card-7', $this->span('2026-11-20 10:00', '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));
        $same->checkOut($this->at('2026-11-20 10:00:00.5'));
        self::assertSame('2026-11-20 15:00:00.000000', $same->startsAt()->format('Y-m-d H:i:s.u'), 'handed over the second it starts, it keeps its start');

        try {
            $loan->checkOut($this->at('2026-11-21'));
            self::fail('A loan out was checked out again.');
        } catch (InvalidLibraryData $invalidLibraryData) {
            self::assertSame('A loan active is not checked out.', $invalidLibraryData->getMessage());
        }

        $expired = Loan::reserve($this->copy(), 'card-7', $this->span('2026-11-20 10:00', '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));

        foreach (['2026-11-27 23:59', '2026-11-28 08:00'] as $moment) {
            try {
                $expired->checkOut($this->at($moment));
                self::fail('A reservation past its due date was checked out.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('A reservation whose due date has come is not checked out: cancel it and lend the copy.', $invalid->getMessage());
            }
        }
    }

    public function testALoanCloses(): void
    {
        $returned = $this->loan();
        $returned->giveBack($this->at('2026-11-20 12:00'));
        self::assertSame([LoanStatus::Returned, '2026-11-20T17:00:00+00:00'], [$returned->status(), $returned->closedAt()?->format(\DATE_ATOM)]);

        $lost = $this->loan();
        $lost->lose($this->at('2026-11-21 12:00'));
        self::assertSame([LoanStatus::Lost, '2026-11-21T17:00:00+00:00'], [$lost->status(), $lost->closedAt()?->format(\DATE_ATOM)]);

        $cancelled = Loan::reserve($this->copy(), 'card-7', $this->span('2026-11-20 10:00', '2026-11-27 23:59'), $this->at('2026-11-13 10:00'));
        $cancelled->cancel($this->at('2026-11-14 12:00'));
        self::assertSame([LoanStatus::Cancelled, '2026-11-14T17:00:00+00:00'], [$cancelled->status(), $cancelled->closedAt()?->format(\DATE_ATOM)]);

        $changes = [
            static function () use ($returned): void { $returned->giveBack(new DateTimeImmutable('2026-11-21')); },
            static function () use ($returned): void { $returned->lose(new DateTimeImmutable('2026-11-21')); },
            static function () use ($returned): void { $returned->cancel(new DateTimeImmutable('2026-11-21')); },
            function (): void { $this->loan()->cancel(new DateTimeImmutable('2026-11-21')); },
            static function () use ($returned): void { $returned->renew(new DateTimeImmutable('2026-12-30')); },
        ];

        foreach ($changes as $change) {
            try {
                $change();
                self::fail('A closed loan changed.');
            } catch (InvalidLibraryData $invalid) {
                self::assertMatchesRegularExpression('/^A loan (returned|active) is not (returned|lost|cancelled|renewed)\.$/', $invalid->getMessage());
            }
        }
    }

    public function testALoanIsRenewedLaterAndItsRemindersStartOver(): void
    {
        $loan = $this->loan();
        $loan->remind(1440);
        $loan->notice($this->at('2026-11-28 10:00'));
        $loan->renew($this->at('2026-12-11 23:59:59'));

        self::assertSame(['2026-12-12T04:59:59+00:00', 1, null, 0, null], [$loan->dueAt()->format(\DATE_ATOM), $loan->renewals(), $loan->lastReminder(), $loan->notices(), $loan->lastNoticeAt()]);

        foreach (['2026-12-11 23:59:59', '2026-12-01'] as $due) {
            try {
                $loan->renew($this->at($due));
                self::fail('A loan was renewed to an earlier date.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('A loan renewed is due later than it was.', $invalid->getMessage());
            }
        }

        self::assertSame(1, $loan->renewals());
    }

    public function testRemindersAndNoticesAreRecorded(): void
    {
        $loan = $this->loan();
        $loan->remind(2880);
        $loan->notice($this->at('2026-11-28 10:00'));
        $loan->notice($this->at('2026-12-05 10:00'));

        self::assertSame([2880, 2, '2026-12-05T15:00:00+00:00'], [$loan->lastReminder(), $loan->notices(), $loan->lastNoticeAt()?->format(\DATE_ATOM)]);

        $loan->forgetMember();
        self::assertNull($loan->member());
    }

    public function testALoanIsOverdueFromItsDueDateWhileOut(): void
    {
        $loan = $this->loan();

        self::assertFalse($loan->isOverdue($this->at('2026-11-27 23:59:58')));
        self::assertTrue($loan->isOverdue($this->at('2026-11-27 23:59:59')));
        self::assertSame($loan->dueAt()->getTimestamp(), $loan->booking($this->at('2026-11-20'))->interval->until);
        self::assertSame($this->at('2026-12-01 10:01')->getTimestamp(), $loan->booking($this->at('2026-12-01 10:00'))->interval->until, 'the copy is held until it comes back');
        self::assertSame([$loan->id, $loan->startsAt()->getTimestamp(), 1], [$loan->booking($this->at('2026-11-20'))->id, $loan->booking($this->at('2026-11-20'))->interval->from, $loan->booking($this->at('2026-11-20'))->quantity]);

        $loan->giveBack($this->at('2026-12-01'));
        self::assertFalse($loan->isOverdue($this->at('2026-12-02')));
    }

    public function testTheEventsNameIdentifiers(): void
    {
        $lent = new CopyLent('loan', 'copy');
        $returned = new CopyReturned('loan', 'copy', CopyCondition::Worn);

        self::assertSame(['loan', 'copy', 'loan', 'copy', CopyCondition::Worn], [$lent->loan, $lent->copy, $returned->loan, $returned->copy, $returned->condition]);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    private function span(string $start, string $end): Interval
    {
        return new Interval($this->at($start), $this->at($end));
    }

    private function title(): Title
    {
        return Title::catalogue(new Record(TitleKind::Game, 'Azul'), $this->at('2026-11-13'));
    }

    private function copy(): Copy
    {
        return Copy::of($this->title(), 'G-1', $this->at('2026-11-13'));
    }

    private function loan(): Loan
    {
        return Loan::lend($this->copy(), 'card-7', $this->at('2026-11-27 23:59:59'), $this->at('2026-11-13 10:00'));
    }
}
