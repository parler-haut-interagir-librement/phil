<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application;

use DateTimeImmutable;
use Phil\LibraryBundle\Application\Contract\ReminderSender;
use Phil\LibraryBundle\Domain\Contract\LoanRepository;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\Reservation\Interval;
use Phil\Reservation\ReminderSchedule;
use Psr\Clock\ClockInterface;

/**
 * The reminders of the active loans: before the due date, so many minutes ahead — a reminder whose moment passed unsent
 * is overtaken by the next one —, then notices of a loan overdue, one every so many days up to a number. Each goes
 * once: the loan records the last sent. No fine is counted: a notice only says the copy is awaited.
 */
final readonly class Reminders
{
    private ReminderSchedule $schedule;

    /**
     * @param list<int> $beforeDue  the minutes before the due date a reminder comes
     * @param int       $everyDays  the days between two notices of a loan overdue
     * @param int       $maxNotices the most notices a loan overdue gets; none with 0
     *
     * @throws InvalidLibraryData when a number is out of its bounds
     */
    public function __construct(
        private LoanRepository $loans,
        private ReminderSender $sender,
        private ClockInterface $clock,
        array $beforeDue = [1440],
        private int $everyDays = 7,
        private int $maxNotices = 3,
    ) {
        $this->schedule = new ReminderSchedule(...$beforeDue);

        if ($everyDays < 1 || $everyDays > 365 || $maxNotices < 0 || $maxNotices > 99) {
            throw InvalidLibraryData::because('Notices of a loan overdue come 1 to 365 days apart, 0 to 99 of them.');
        }
    }

    /**
     * Sends the reminders and notices due now, for at most so many loans.
     *
     * @return int the reminders and notices sent
     */
    public function send(int $batch): int
    {
        $now = $this->clock->now();
        $horizon = $now->modify(\sprintf('+%d minutes', $this->schedule->offsets[0] ?? 0));
        $sent = 0;

        foreach ($this->loans->dueBefore($horizon, max(1, $batch)) as $loan) {
            if ($this->remind($loan, $now) || $this->notice($loan, $now)) {
                $this->loans->save($loan);
                ++$sent;
            }
        }

        return $sent;
    }

    private function remind(Loan $loan, DateTimeImmutable $now): bool
    {
        $due = $loan->dueAt();
        $offset = $this->schedule->due(new Interval($due, $due->modify('+1 minute')), $now, $loan->lastReminder());

        if (null === $offset) {
            return false;
        }

        $this->sender->beforeDue($loan->id, $offset);
        $loan->remind($offset);

        return true;
    }

    private function notice(Loan $loan, DateTimeImmutable $now): bool
    {
        if (!$loan->isOverdue($now) || $loan->notices() >= $this->maxNotices) {
            return false;
        }

        $last = $loan->lastNoticeAt();

        if ($last instanceof DateTimeImmutable && $now->getTimestamp() < $last->modify(\sprintf('+%d days', $this->everyDays))->getTimestamp()) {
            return false;
        }

        $this->sender->overdue($loan->id, $loan->notices() + 1);
        $loan->notice($now);

        return true;
    }
}
