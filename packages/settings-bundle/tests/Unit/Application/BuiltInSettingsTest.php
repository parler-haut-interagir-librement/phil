<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Application;

use Phil\SettingsBundle\Application\BuiltInSettings;
use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuiltInSettings::class)]
final class BuiltInSettingsTest extends TestCase
{
    public function testTheFourSectionsInTheirOrder(): void
    {
        self::assertSame(
            [['general', 'section.general', 'phil_settings'], ['notifications', 'section.notifications', 'phil_settings'], ['privacy', 'section.privacy', 'phil_settings'], ['display', 'section.display', 'phil_settings']],
            array_map(static fn (SectionDefinition $section): array => [$section->key, $section->label, $section->translationDomain], BuiltInSettings::sections()),
        );
    }

    public function testTheDisplayPreferencesAreKeptForVisitorsAndWrittenOnThePage(): void
    {
        self::assertSame([
            ['display.theme', ['system', 'light', 'dark'], 'theme'],
            ['display.motion', ['system', 'reduce'], 'motion'],
            ['display.contrast', ['system', 'more'], 'contrast'],
            ['display.text_size', ['system', 'large', 'larger'], 'text-size'],
        ], array_map(static fn (SettingDefinition $setting): array => [$setting->key, $setting->choices, $setting->htmlAttribute], BuiltInSettings::display()));

        foreach (BuiltInSettings::display() as $setting) {
            self::assertSame([SettingType::Choice, 'system', Scope::User, 'display', 'phil_settings', true], [$setting->type, $setting->default, $setting->scope, $setting->section, $setting->translationDomain, $setting->anonymous]);
        }
    }

    public function testTheNotificationPreferences(): void
    {
        [$digest, $email, $push, $start, $end] = BuiltInSettings::notifications();

        self::assertSame(['notifications.digest', SettingType::Choice, 'never', ['never', 'daily', 'weekly']], [$digest->key, $digest->type, $digest->default, $digest->choices]);
        self::assertSame(['notifications.email', SettingType::Boolean, true], [$email->key, $email->type, $email->default]);
        self::assertSame(['notifications.web_push', SettingType::Boolean, true], [$push->key, $push->type, $push->default]);
        self::assertSame(['notifications.quiet_start', SettingType::Text, '', 5, 'notifications.quiet_start.help'], [$start->key, $start->type, $start->default, $start->maxLength, $start->help]);
        self::assertSame(['notifications.quiet_end', SettingType::Text, '', 5, null], [$end->key, $end->type, $end->default, $end->maxLength, $end->help]);

        foreach (BuiltInSettings::notifications() as $setting) {
            self::assertSame(['notifications', 'phil_settings', false], [$setting->section, $setting->translationDomain, $setting->anonymous]);
        }

        self::assertSame('23:59', $start->normalize('23:59'));
        self::assertSame('00:00', $end->normalize('00:00'));

        foreach (['24:00', '7:00', '07:60', 'x7:00', '07:0x', '7:00 '] as $refused) {
            try {
                $start->normalize($refused);
                self::fail($refused);
            } catch (InvalidSettingValue $invalidSettingValue) {
                self::assertSame('phil_settings.error.pattern', $invalidSettingValue->reason);
            }
        }
    }
}
