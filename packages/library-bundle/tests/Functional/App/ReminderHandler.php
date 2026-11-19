<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Functional\App;

use Phil\LibraryBundle\Application\Message\SendLoanReminder;

/**
 * The application's handler of the reminders, which writes down what it got.
 */
final class ReminderHandler
{
    /** @var list<SendLoanReminder> */
    public array $messages = [];

    public function __invoke(SendLoanReminder $message): void
    {
        $this->messages[] = $message;
    }
}
