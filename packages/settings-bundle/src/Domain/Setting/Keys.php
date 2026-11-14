<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Setting;

use Phil\SettingsBundle\Domain\Exception\InvalidSetting;

/**
 * The shapes of the names the bundle stores, puts in a URL, a form or a cookie, so that none needs escaping: a
 * setting's key is dotted words, `display.motion` or `author.signature`; a section's key one word; a choice a word
 * with hyphens; an HTML attribute the part after `data-`.
 */
final class Keys
{
    /** The longest key of a setting, the length of its column. */
    public const int SETTING_LENGTH = 128;

    public const string SETTING = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/D';

    public const string SECTION = '/^[a-z][a-z0-9_]{0,63}$/D';

    public const string CHOICE = '/^[a-z0-9][a-z0-9_-]{0,63}$/D';

    public const string ATTRIBUTE = '/^[a-z][a-z0-9-]{0,31}$/D';

    /**
     * @throws InvalidSetting when the key is not dotted lowercase words of at most 128 characters
     */
    public static function setting(string $key): string
    {
        if (\strlen($key) > self::SETTING_LENGTH || 1 !== preg_match(self::SETTING, $key)) {
            throw InvalidSetting::because(\sprintf('"%s" is not the key of a setting: dotted lowercase words, such as "display.motion", of at most %d characters.', $key, self::SETTING_LENGTH));
        }

        return $key;
    }

    /**
     * @throws InvalidSetting when the key is not one lowercase word of at most 64 characters
     */
    public static function section(string $key): string
    {
        if (1 !== preg_match(self::SECTION, $key)) {
            throw InvalidSetting::because(\sprintf('"%s" is not the key of a section: a lowercase word, such as "display", of at most 64 characters.', $key));
        }

        return $key;
    }
}
