<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Attribute\AsSettingSection;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SettingType;

#[AsSettingSection('author', 'author.title', 'author.help', 'app', ['notifications'], ['general'])]
#[AsSetting('author.signature', SettingType::Text, 'Ada', Scope::User, 'author', 'signature', 'signature.help', 'app', [], null, null, 40, '/^\p{L}*$/u', null, false, null, ['author.columns'], [])]
#[AsSetting('author.columns', SettingType::Integer, 2, section: 'author', min: 1, max: 4, after: ['author.signature'])]
#[AsSetting('author.mode', SettingType::Choice, section: 'author', choices: ['plain', 'rich'], anonymous: true, htmlAttribute: 'mode')]
#[AsSetting('author.open', SettingType::Boolean, true, Scope::Site, 'author', role: 'ROLE_EDITOR')]
final class AuthorSettings
{
}
