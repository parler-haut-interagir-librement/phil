<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Application;

use Override;
use Phil\LibraryBundle\Application\Reminders;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Tests\Unit\Fixture\Library;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reminders::class)]
final class RemindersTest extends TestCase
{
    private Library $library;

    #[Override]
    protected function setUp(): void
    {
        $this->library = new Library();
        $this->library->shelve('Azul', ['G-1', 'G-2']);
    }

    public function testRemindersComeBeforeTheDueDateEachOnce(): void
    {
        $loan = $this->library->service->lend('G-1', 'card-7');
        $reminders = $this->library->reminders();
        $clock = $this->library->clock;

        self::assertSame(0, $reminders->send(10), 'two weeks ahead, nothing');
        $clock->modify('2026-11-25 23:59:58');
        self::assertSame(0, $reminders->send(10), 'a second before two days ahead');
        $clock->modify('2026-11-25 23:59:59');
        self::assertSame(1, $reminders->send(10));
        self::assertSame(0, $reminders->send(10), 'sent once');
        $clock->modify('2026-11-26 23:59:59');
        self::assertSame(1, $reminders->send(10));
        self::assertSame(['due '.$loan->id.' 2880', 'due '.$loan->id.' 1440'], $this->library->recorders->lines);
        self::assertSame(1440, $loan->lastReminder());
        self::assertSame([$loan->id, $loan->id, $loan->id], $this->library->loans->saved);
    }

    public function testAReminderPassedIsOvertakenByTheNext(): void
    {
        $loan = $this->library->service->lend('G-1', 'card-7');
        $this->library->clock->modify('2026-11-27 12:00');

        self::assertSame(1, $this->library->reminders()->send(10));
        self::assertSame(['due '.$loan->id.' 1440'], $this->library->recorders->lines);
    }

    public function testNoticesComeOnceOverdueThenEverySoManyDaysUpToTheMost(): void
    {
        $loan = $this->library->service->lend('G-1', 'card-7');
        $loan->remind(1440);

        $reminders = $this->library->reminders([1440], 7, 2);
        $clock = $this->library->clock;

        $clock->modify('2026-11-27 23:59:58');
        self::assertSame(0, $reminders->send(10));
        $clock->modify('2026-11-27 23:59:59');
        self::assertSame(1, $reminders->send(10));
        self::assertSame(0, $reminders->send(10));
        $clock->modify('2026-12-04 23:59:58');
        self::assertSame(0, $reminders->send(10), 'a second short of a week');
        $clock->modify('2026-12-04 23:59:59');
        self::assertSame(1, $reminders->send(10));
        $clock->modify('2026-12-30');
        self::assertSame(0, $reminders->send(10), 'two notices at most');
        self::assertSame(['overdue '.$loan->id.' 1', 'overdue '.$loan->id.' 2'], $this->library->recorders->lines);
        self::assertSame([2, '2026-12-05T04:59:59+00:00'], [$loan->notices(), $loan->lastNoticeAt()?->format(\DATE_ATOM)]);
    }

    public function testTheDefaultsRemindADayAheadThenNoticeEveryWeekThreeTimes(): void
    {
        $loan = $this->library->service->lend('G-1', 'card-7');
        $reminders = new Reminders($this->library->loans, $this->library->recorders, $this->library->clock);
        $clock = $this->library->clock;

        $clock->modify('2026-11-26 23:59:58');
        self::assertSame(0, $reminders->send(10), 'a second before the day ahead');
        $clock->modify('2026-11-26 23:59:59');
        self::assertSame(1, $reminders->send(10));
        self::assertSame(['due '.$loan->id.' 1440'], $this->library->recorders->lines);

        $clock->modify('2026-11-27 23:59:59');
        self::assertSame(1, $reminders->send(10));
        $clock->modify('2026-12-04 23:59:58');
        self::assertSame(0, $reminders->send(10), 'a second short of a week');
        $clock->modify('2026-12-04 23:59:59');
        self::assertSame(1, $reminders->send(10));
        $clock->modify('2026-12-11 23:59:59');
        self::assertSame(1, $reminders->send(10));
        $clock->modify('2026-12-18 23:59:59');
        self::assertSame(0, $reminders->send(10), 'three notices at most');
        self::assertSame(3, $loan->notices());
    }

    public function testTheLoansDueAreAskedForUpToTheFirstReminder(): void
    {
        $this->library->reminders([], 7, 3)->send(10);
        $this->library->reminders([2880, 1440])->send(10);

        [$none, $some] = $this->library->loans->horizons;
        self::assertSame($this->library->clock->now()->getTimestamp(), $none->getTimestamp(), 'no reminder: those due now');
        self::assertSame($this->library->clock->now()->modify('+2880 minutes')->getTimestamp(), $some->getTimestamp());
    }

    public function testNoNoticeWithoutAMost(): void
    {
        $this->library->service->lend('G-1', 'card-7');
        $this->library->clock->modify('2026-12-30');

        self::assertSame(0, $this->library->reminders([], 7, 0)->send(10));
        self::assertSame([], $this->library->recorders->lines);
    }

    public function testAReturnedLoanIsNotReminded(): void
    {
        $this->library->service->lend('G-1', 'card-7');
        $this->library->service->giveBack('G-1');
        $this->library->clock->modify('2026-11-27 12:00');

        self::assertSame(0, $this->library->reminders()->send(10));
    }

    public function testABatchBoundsTheLoansRead(): void
    {
        $this->library->service->lend('G-1', 'card-7');
        $this->library->service->lend('G-2', 'card-8');
        $this->library->clock->modify('2026-11-27 12:00');

        self::assertSame(1, $this->library->reminders()->send(0), 'one at least');
        self::assertSame(1, $this->library->reminders()->send(10), 'the other, the first being sent');
        self::assertSame(0, $this->library->reminders()->send(10));
    }

    public function testTheBoundsOfTheNotices(): void
    {
        foreach ([[0, 3], [366, 3], [7, -1], [7, 100]] as [$every, $max]) {
            try {
                $this->library->reminders([1440], $every, $max);
                self::fail('Notices out of bounds were set.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('Notices of a loan overdue come 1 to 365 days apart, 0 to 99 of them.', $invalid->getMessage());
            }
        }

        self::assertInstanceOf(Reminders::class, $this->library->reminders([1440], 1, 99));
        self::assertInstanceOf(Reminders::class, $this->library->reminders([1440], 365, 0));
    }
}
