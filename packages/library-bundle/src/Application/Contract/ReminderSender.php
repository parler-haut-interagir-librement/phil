<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Contract;

/**
 * Sends the reminders of a loan, by identifiers: the application reads the loan and its member, and writes to them as
 * it does.
 */
interface ReminderSender
{
    /**
     * @param int $offset the minutes before the due date this reminder comes
     */
    public function beforeDue(string $loan, int $offset): void;

    /**
     * @param int $notice the number of this notice of the loan overdue, from 1
     */
    public function overdue(string $loan, int $notice): void;
}
