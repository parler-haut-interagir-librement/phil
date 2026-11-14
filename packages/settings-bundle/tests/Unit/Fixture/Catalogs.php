<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Phil\SettingsBundle\Application\BuiltInSettings;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;

/**
 * A catalog of every kind of setting: the bundle's own, an author's section of each type, and the site's settings.
 */
final class Catalogs
{
    public static function sample(): SettingCatalog
    {
        return new SettingCatalog(
            [...BuiltInSettings::sections(), new SectionDefinition('author', before: ['notifications']), new SectionDefinition('site')],
            [
                ...BuiltInSettings::display(),
                ...BuiltInSettings::notifications(),
                new SettingDefinition('author.signature', SettingType::Text, section: 'author', maxLength: 20),
                new SettingDefinition('author.columns', SettingType::Integer, 2, section: 'author', min: 1, max: 4),
                new SettingDefinition('author.public', SettingType::Boolean, section: 'author', anonymous: true),
                new SettingDefinition('author.genres', SettingType::Choices, ['fantasy'], section: 'author', choices: ['fantasy', 'horror', 'science']),
                new SettingDefinition('site.open', SettingType::Boolean, true, Scope::Site, 'site'),
                new SettingDefinition('site.motto', SettingType::Text, 'Hello', Scope::Site, 'site', role: 'ROLE_EDITOR'),
            ],
        );
    }
}
