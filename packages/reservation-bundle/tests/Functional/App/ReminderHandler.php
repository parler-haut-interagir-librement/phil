<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Functional\App;

use Phil\ReservationBundle\Application\Message\SendReservationReminder;

/**
 * The application's handler of the reminders: it keeps them for the test to read.
 */
final class ReminderHandler
{
    /** @var list<SendReservationReminder> */
    public array $handled = [];

    public function __invoke(SendReservationReminder $message): void
    {
        $this->handled[] = $message;
    }
}
