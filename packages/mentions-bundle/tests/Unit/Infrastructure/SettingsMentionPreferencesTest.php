<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use Phil\MentionsBundle\Infrastructure\Settings\SettingsMentionPreferences;
use Phil\MentionsBundle\Tests\Unit\Fixture\User;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SettingsMentionPreferences::class)]
final class SettingsMentionPreferencesTest extends TestCase
{
    public function testAMemberTurnsTheirMentionsOff(): void
    {
        $catalog = new SettingCatalog([new SectionDefinition('notifications')], [new SettingDefinition(SettingsMentionPreferences::SETTING, SettingType::Boolean, true, section: 'notifications')]);
        $settings = new Settings($catalog, new InMemorySettingRepository(), new User(), new User(), new MockClock());
        $settings->save(Subject::user('u-2'), [SettingsMentionPreferences::SETTING => false]);

        $preferences = new SettingsMentionPreferences($settings);

        self::assertTrue($preferences->notifies('u-1'));
        self::assertFalse($preferences->notifies('u-2'));
    }
}
