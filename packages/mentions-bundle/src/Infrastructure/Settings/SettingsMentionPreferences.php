<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Settings;

use Override;
use Phil\MentionsBundle\Application\Contract\MentionPreferences;
use Phil\SettingsBundle\Application\Settings;

/**
 * The members' choice in their preferences, the setting `notifications.mentions` of the notifications section, on by
 * default; a member is a user of the settings by the identifier their notifications go to.
 */
final readonly class SettingsMentionPreferences implements MentionPreferences
{
    public const string SETTING = 'notifications.mentions';

    public function __construct(
        private Settings $settings,
    ) {
    }

    #[Override]
    public function notifies(string $recipient): bool
    {
        return $this->settings->bool(self::SETTING, $recipient);
    }
}
