<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

use Phil\LocalPickup\ReadyNotice;

/**
 * Sends the customer the "ready for pickup" notice — through the application's notifications, by e-mail, push or
 * text. The notice holds the code: it is sent and never stored.
 */
interface ReadyNotifier
{
    public function notify(ReadyNotice $notice): void;
}
