<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Application;

use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\UnknownSetting;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingCatalog::class)]
final class SettingCatalogTest extends TestCase
{
    public function testSectionsAndSettingsAreInTheirOrder(): void
    {
        $catalog = $this->catalog();

        self::assertSame(['author', 'general'], array_map(static fn (SectionDefinition $section): string => $section->key, $catalog->sections(Scope::User)));
        self::assertSame(['general', 'site'], array_map(static fn (SectionDefinition $section): string => $section->key, $catalog->sections(Scope::Site)));
        self::assertSame(['author.name', 'author.bio', 'general.a', 'general.b', 'general.open', 'site.motto'], $this->keys($catalog->settings()));
        self::assertSame(['author.name', 'author.bio', 'general.a', 'general.b'], $this->keys($catalog->settings(Scope::User)));
        self::assertSame(['general.open', 'site.motto'], $this->keys($catalog->settings(Scope::Site)));
        self::assertSame(['general.a', 'general.b', 'general.open'], $this->keys($catalog->settings(section: 'general')));
        self::assertSame(['general.open'], $this->keys($catalog->settings(Scope::Site, 'general')));
        self::assertSame([], $this->keys($catalog->settings(Scope::User, 'empty')));
    }

    public function testASettingOrASectionIsFoundByItsKey(): void
    {
        $catalog = $this->catalog();

        self::assertSame('author.bio', $catalog->get('author.bio')->key);
        self::assertTrue($catalog->has('author.bio'));
        self::assertFalse($catalog->has('author.age'));
        self::assertSame('empty', $catalog->section('empty')->key);
    }

    public function testTheUnknownAreRefused(): void
    {
        try {
            $this->catalog()->get('author.age');
            self::fail('An unknown setting is found.');
        } catch (UnknownSetting $unknownSetting) {
            self::assertSame('No setting is declared under the key "author.age".', $unknownSetting->getMessage());
        }

        $this->expectException(UnknownSetting::class);
        $this->expectExceptionMessageIsOrContains('No section is declared under the key "nowhere".');

        $this->catalog()->section('nowhere');
    }

    public function testASectionTwiceIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('The section "a" is declared twice.');

        new SettingCatalog([new SectionDefinition('a'), new SectionDefinition('a')], []);
    }

    public function testASettingTwiceIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('The setting "a.b" is declared twice.');

        new SettingCatalog([new SectionDefinition('a')], [new SettingDefinition('a.b', SettingType::Boolean, section: 'a'), new SettingDefinition('a.b', SettingType::Text, section: 'a')]);
    }

    public function testASettingInASectionNotDeclaredIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('The setting "a.b" is in the section "b", which is not declared.');

        new SettingCatalog([new SectionDefinition('a')], [new SettingDefinition('a.b', SettingType::Boolean, section: 'b')]);
    }

    public function testACircularOrderIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('"a.b", "a.c" ask to come before one another in a circle.');

        new SettingCatalog([new SectionDefinition('a')], [
            new SettingDefinition('a.b', SettingType::Boolean, section: 'a', after: ['a.c']),
            new SettingDefinition('a.c', SettingType::Boolean, section: 'a', after: ['a.b']),
        ]);
    }

    private function catalog(): SettingCatalog
    {
        return new SettingCatalog(
            [new SectionDefinition('general'), new SectionDefinition('site'), new SectionDefinition('empty'), new SectionDefinition('author', before: ['general'])],
            [
                new SettingDefinition('general.b', SettingType::Boolean, after: ['general.a']),
                new SettingDefinition('general.a', SettingType::Boolean),
                new SettingDefinition('site.motto', SettingType::Text, scope: Scope::Site, section: 'site'),
                new SettingDefinition('general.open', SettingType::Boolean, scope: Scope::Site),
                new SettingDefinition('author.bio', SettingType::Text, section: 'author'),
                new SettingDefinition('author.name', SettingType::Text, section: 'author', before: ['author.bio']),
            ],
        );
    }

    /**
     * @param list<SettingDefinition> $settings
     *
     * @return list<string>
     */
    private function keys(array $settings): array
    {
        return array_map(static fn (SettingDefinition $setting): string => $setting->key, $settings);
    }

    public function testTheSettingsOfTheRootElementAreKeptApartInTheirOrder(): void
    {
        self::assertSame(
            ['display.theme', 'display.motion', 'display.contrast', 'display.text_size'],
            array_map(static fn (SettingDefinition $setting): string => $setting->key, Catalogs::sample()->withHtmlAttribute()),
        );
    }
}
