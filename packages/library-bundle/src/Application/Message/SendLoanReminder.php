<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Message;

/**
 * The Messenger message of a reminder due: identifiers only, the application's handler reads the loan and its member.
 * `kind` is `due`, a reminder before the due date whose `step` is its offset in minutes, or `overdue`, a notice whose
 * `step` is its number from 1.
 */
final readonly class SendLoanReminder
{
    public const string DUE = 'due';

    public const string OVERDUE = 'overdue';

    public function __construct(
        public string $loanId,
        public string $kind,
        public int $step,
    ) {
    }
}
