<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

use Phil\FormsBundle\Domain\Model\Entry;

/**
 * Tells someone of an entry: by email, by webhook, or as the application wishes. A notifier with nothing to do for
 * this form returns.
 */
interface EntryNotifier
{
    /**
     * @throws NotificationFailed when whoever should hear of it did not
     */
    public function notify(Entry $entry): void;
}
