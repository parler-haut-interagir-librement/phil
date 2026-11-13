<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Contract;

use Phil\AccessLinks\IssuedLink;

/**
 * How the application sends a reminder: it finds how to reach the recipient from their stable identifier — an email
 * address, a notification channel — and sends the fresh link it is given. The library knows no address.
 */
interface ReminderSender
{
    public function sendReminder(IssuedLink $link): void;
}
