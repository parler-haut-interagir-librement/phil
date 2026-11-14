<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Notifications;

use Override;
use Phil\NotificationsBundle\Application\Channels;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\QuietHours;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\SettingsBundle\Application\BuiltInSettings;
use Phil\SettingsBundle\Application\Settings;

/**
 * The notifications bundle's preferences, read from the notification settings of the preferences: the digest, email
 * and Web Push turned off for every type, and the quiet hours when both their start and end are given, in the time
 * zone of the configuration. A recipient is a user, by the same identifier.
 */
final readonly class SettingsNotificationPreferences implements NotificationPreferences
{
    public function __construct(
        private Settings $settings,
        private string $timezone = 'UTC',
    ) {
    }

    #[Override]
    public function settingsFor(string $recipient): RecipientSettings
    {
        $optOuts = [];

        foreach ([BuiltInSettings::EMAIL => Channels::EMAIL, BuiltInSettings::WEB_PUSH => Channels::WEB_PUSH] as $key => $channel) {
            if (!$this->settings->bool($key, $recipient)) {
                $optOuts[] = '*:'.$channel;
            }
        }

        $start = $this->settings->string(BuiltInSettings::QUIET_START, $recipient);
        $end = $this->settings->string(BuiltInSettings::QUIET_END, $recipient);

        return new RecipientSettings(
            DigestFrequency::from($this->settings->string(BuiltInSettings::DIGEST, $recipient)),
            '' === $start || '' === $end ? null : QuietHours::of($start, $end, $this->timezone),
            $optOuts,
        );
    }
}
