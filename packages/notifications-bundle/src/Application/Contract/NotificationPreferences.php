<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;

/**
 * What each recipient chose about their notifications. The bundle reads it as each notification is delivered and as
 * each digest is sent: an implementation answers fast, from a cache if it must, and is replaced by the application's
 * own settings when it has them.
 */
interface NotificationPreferences
{
    public function settingsFor(string $recipient): RecipientSettings;
}
