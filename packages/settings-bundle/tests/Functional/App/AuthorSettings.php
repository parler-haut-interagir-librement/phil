<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Functional\App;

use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Attribute\AsSettingSection;
use Phil\SettingsBundle\Domain\Setting\SettingType;

/**
 * The settings an application declares with attributes, found by autoconfiguration.
 */
#[AsSettingSection('author', before: ['notifications'])]
#[AsSetting('author.signature', SettingType::Text, section: 'author', maxLength: 20)]
final class AuthorSettings
{
}
