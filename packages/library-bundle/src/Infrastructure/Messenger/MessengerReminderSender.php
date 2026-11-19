<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Messenger;

use Override;
use Phil\LibraryBundle\Application\Contract\ReminderSender;
use Phil\LibraryBundle\Application\Message\SendLoanReminder;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The reminders as Messenger messages, identifiers only: the application's handler reads the loan and its member and
 * writes to them, on the transport it routes the message to.
 */
final readonly class MessengerReminderSender implements ReminderSender
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[Override]
    public function beforeDue(string $loan, int $offset): void
    {
        $this->bus->dispatch(new SendLoanReminder($loan, SendLoanReminder::DUE, $offset));
    }

    #[Override]
    public function overdue(string $loan, int $notice): void
    {
        $this->bus->dispatch(new SendLoanReminder($loan, SendLoanReminder::OVERDUE, $notice));
    }
}
