<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;

/**
 * Settings by recipient, the defaults for the others.
 */
final class Preferences implements NotificationPreferences
{
    /**
     * @param array<array-key, RecipientSettings> $settings
     */
    public function __construct(
        public array $settings = [],
    ) {
    }

    #[Override]
    public function settingsFor(string $recipient): RecipientSettings
    {
        return $this->settings[$recipient] ?? new RecipientSettings();
    }
}
