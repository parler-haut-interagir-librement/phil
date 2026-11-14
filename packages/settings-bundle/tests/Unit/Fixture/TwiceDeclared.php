<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Setting\SettingType;

#[AsSetting('display.motion', SettingType::Boolean)]
final class TwiceDeclared
{
}
