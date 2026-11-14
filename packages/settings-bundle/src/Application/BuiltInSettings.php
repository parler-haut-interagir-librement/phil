<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application;

use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;

/**
 * What the bundle declares itself: four sections, in this order, which packages and the application fill — general,
 * notifications, privacy, display; a section without settings is not shown —; the display preferences every page
 * applies; and the notification preferences, when the notifications bundle is installed.
 */
final class BuiltInSettings
{
    public const string DOMAIN = 'phil_settings';

    public const string THEME = 'display.theme';

    public const string MOTION = 'display.motion';

    public const string CONTRAST = 'display.contrast';

    public const string TEXT_SIZE = 'display.text_size';

    public const string DIGEST = 'notifications.digest';

    public const string EMAIL = 'notifications.email';

    public const string WEB_PUSH = 'notifications.web_push';

    public const string QUIET_START = 'notifications.quiet_start';

    public const string QUIET_END = 'notifications.quiet_end';

    /** A time on a 24-hour clock, `HH:MM`, or nothing. */
    public const string TIME = '/^(([01]\d|2[0-3]):[0-5]\d)?$/D';

    /**
     * @return list<SectionDefinition>
     */
    public static function sections(): array
    {
        return array_map(
            static fn (string $key): SectionDefinition => new SectionDefinition($key, translationDomain: self::DOMAIN),
            ['general', 'notifications', 'privacy', 'display'],
        );
    }

    /**
     * The display preferences, kept for visitors too, each written on the page's root element unless it follows the
     * system: the colour scheme as `data-theme`, reduced motion as `data-motion`, more contrast as `data-contrast`
     * and a larger text as `data-text-size`.
     *
     * @return list<SettingDefinition>
     */
    public static function display(): array
    {
        return [
            self::displayed(self::THEME, ['system', 'light', 'dark'], 'theme'),
            self::displayed(self::MOTION, ['system', 'reduce'], 'motion'),
            self::displayed(self::CONTRAST, ['system', 'more'], 'contrast'),
            self::displayed(self::TEXT_SIZE, ['system', 'large', 'larger'], 'text-size'),
        ];
    }

    /**
     * The notification preferences: the digest, email and Web Push turned on or off for every type, and the quiet
     * hours.
     *
     * @return list<SettingDefinition>
     */
    public static function notifications(): array
    {
        return [
            new SettingDefinition(self::DIGEST, SettingType::Choice, 'never', section: 'notifications', translationDomain: self::DOMAIN, choices: ['never', 'daily', 'weekly']),
            new SettingDefinition(self::EMAIL, SettingType::Boolean, true, section: 'notifications', translationDomain: self::DOMAIN),
            new SettingDefinition(self::WEB_PUSH, SettingType::Boolean, true, section: 'notifications', translationDomain: self::DOMAIN),
            new SettingDefinition(self::QUIET_START, SettingType::Text, section: 'notifications', help: self::QUIET_START.'.help', translationDomain: self::DOMAIN, maxLength: 5, pattern: self::TIME),
            new SettingDefinition(self::QUIET_END, SettingType::Text, section: 'notifications', translationDomain: self::DOMAIN, maxLength: 5, pattern: self::TIME),
        ];
    }

    /**
     * @param list<string> $choices
     */
    private static function displayed(string $key, array $choices, string $attribute): SettingDefinition
    {
        return new SettingDefinition($key, SettingType::Choice, section: 'display', translationDomain: self::DOMAIN, choices: $choices, anonymous: true, htmlAttribute: $attribute);
    }
}
