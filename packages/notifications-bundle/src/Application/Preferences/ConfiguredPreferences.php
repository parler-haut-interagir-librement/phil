<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Preferences;

use Override;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;

/**
 * The same settings for every recipient: the defaults of the configuration.
 */
final readonly class ConfiguredPreferences implements NotificationPreferences
{
    public function __construct(
        private RecipientSettings $defaults = new RecipientSettings(),
    ) {
    }

    #[Override]
    public function settingsFor(string $recipient): RecipientSettings
    {
        return $this->defaults;
    }
}
