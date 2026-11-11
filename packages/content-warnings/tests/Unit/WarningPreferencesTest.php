<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Category;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Exception\InvalidWarning;
use Phil\ContentWarnings\Exception\UnreadableWarnings;
use Phil\ContentWarnings\LocalizedText;
use Phil\ContentWarnings\Preference;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\Theme;
use Phil\ContentWarnings\WarningPreferences;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WarningPreferences::class)]
#[CoversClass(Preference::class)]
final class WarningPreferencesTest extends TestCase
{
    public function testAPreferenceAppliesFromItsSeverityOn(): void
    {
        $fromModerate = new Preference(Display::Hide, Severity::Moderate);

        self::assertFalse($fromModerate->appliesTo(new ContentWarning('gore', Severity::Mild)));
        self::assertTrue($fromModerate->appliesTo(new ContentWarning('gore', Severity::Moderate)));
        self::assertTrue($fromModerate->appliesTo(new ContentWarning('gore', Severity::Severe)));
        self::assertTrue($fromModerate->appliesTo(new ContentWarning('gore')));
        self::assertTrue(new Preference(Display::Fold)->appliesTo(new ContentWarning('gore', Severity::Mild)));
    }

    public function testWarnedContentIsFoldedByDefault(): void
    {
        self::assertSame(Display::Fold, WarningPreferences::defaults()->default);
        self::assertSame(Display::Show, WarningPreferences::defaults(Display::Show)->default);
        self::assertSame(Display::Hide, WarningPreferences::defaults()->withDefault(Display::Hide)->default);
        self::assertSame(Display::Fold, WarningPreferences::defaults()->displayFor(new ContentWarning('gore')));
    }

    public function testTheThemeWinsOverTheCategoryWhichWinsOverTheDefault(): void
    {
        $preferences = WarningPreferences::defaults(Display::Show)
            ->withCategory('violence', Display::Fold)
            ->withTheme('gore', Display::Hide);

        self::assertSame(Display::Hide, $preferences->displayFor(new ContentWarning('gore'), $this->catalogue()));
        self::assertSame(Display::Fold, $preferences->displayFor(new ContentWarning('war'), $this->catalogue()));
        self::assertSame(Display::Show, $preferences->displayFor(new ContentWarning('spiders'), $this->catalogue()));
        self::assertSame(Display::Show, $preferences->displayFor(new ContentWarning('war')));
        self::assertSame(Display::Show, $preferences->displayFor(new ContentWarning('unknown'), $this->catalogue()));
        self::assertSame(Display::Hide, $preferences->displayFor(new ContentWarning('gore')));
    }

    public function testAPreferenceBelowItsSeverityGivesWayToTheNext(): void
    {
        $preferences = WarningPreferences::defaults(Display::Show)
            ->withCategory('violence', Display::Fold, Severity::Moderate)
            ->withTheme('gore', Display::Hide, Severity::Severe);

        self::assertSame(Display::Hide, $preferences->displayFor(new ContentWarning('gore', Severity::Severe), $this->catalogue()));
        self::assertSame(Display::Fold, $preferences->displayFor(new ContentWarning('gore', Severity::Moderate), $this->catalogue()));
        self::assertSame(Display::Show, $preferences->displayFor(new ContentWarning('gore', Severity::Mild), $this->catalogue()));
    }

    public function testPreferencesAreAddedReplacedAndRemoved(): void
    {
        $preferences = WarningPreferences::defaults()
            ->withTheme('gore', Display::Fold)
            ->withTheme('spiders', Display::Hide)
            ->withTheme('gore', Display::Hide, Severity::Moderate)
            ->withTheme('clowns', Display::Show)
            ->withCategory('violence', Display::Hide)
            ->withCategory('phobias', Display::Show);

        self::assertEquals(new Preference(Display::Hide, Severity::Moderate), $preferences->theme('gore'));
        self::assertNull($preferences->theme('war'));
        self::assertEquals(new Preference(Display::Hide), $preferences->category('violence'));
        self::assertNull($preferences->category('animals'));
        self::assertSame(['gore', 'spiders', 'clowns'], array_keys($preferences->themes()));
        self::assertSame(['violence', 'phobias'], array_keys($preferences->categories()));
        self::assertSame(['gore', 'spiders'], $preferences->themesWith(Display::Hide));
        self::assertSame(['clowns'], $preferences->themesWith(Display::Show));
        self::assertSame([], $preferences->themesWith(Display::Fold));
        self::assertSame(['2024'], WarningPreferences::defaults()->withTheme('2024', Display::Hide)->themesWith(Display::Hide));
        self::assertSame(['spiders', 'clowns'], array_keys($preferences->withoutTheme('gore')->themes()));
        self::assertSame(['phobias'], array_keys($preferences->withoutCategory('violence')->categories()));
        self::assertSame(Display::Fold, $preferences->withoutTheme('gore')->withoutCategory('violence')->default);
        self::assertSame(['violence', 'phobias'], array_keys($preferences->withoutTheme('gore')->categories()));
        self::assertSame(['gore', 'spiders', 'clowns'], array_keys($preferences->withoutCategory('violence')->themes()));
        self::assertSame(['gore', 'spiders', 'clowns'], array_keys($preferences->withDefault(Display::Show)->themes()));
        self::assertSame(['violence', 'phobias'], array_keys($preferences->withDefault(Display::Show)->categories()));
        self::assertSame(['violence', 'phobias'], array_keys($preferences->withTheme('war', Display::Hide)->categories()));
        self::assertSame(['gore', 'spiders', 'clowns'], array_keys($preferences->withCategory('animals', Display::Hide)->themes()));
        self::assertSame(Display::Fold, $preferences->withTheme('war', Display::Hide)->withCategory('animals', Display::Hide)->default);
    }

    public function testAMalformedKeyIsRefused(): void
    {
        $this->expectException(InvalidWarning::class);
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('É', 80).'" is not a theme or category key');

        WarningPreferences::defaults()->withCategory(str_repeat('É', 81), Display::Hide);
    }

    public function testAMalformedThemeKeyIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('"Gore" is not a theme or category key');

        WarningPreferences::defaults()->withTheme('Gore', Display::Hide);
    }

    public function testPreferencesHaveAtMostTwoThousandTwoHundredEntries(): void
    {
        $preferences = WarningPreferences::defaults();

        for ($index = 1; $index <= 2000; ++$index) {
            $preferences = $preferences->withTheme('t'.$index, Display::Hide);
        }

        for ($index = 1; $index <= 200; ++$index) {
            $preferences = $preferences->withCategory('c'.$index, Display::Hide);
        }

        self::assertCount(2000, $preferences->themes());
        $this->expectExceptionMessageIsOrContains('preferences have at most 2200 entries');
        $preferences->withTheme('t2001', Display::Hide);
    }

    public function testPreferencesAreStoredAndReadBack(): void
    {
        $preferences = WarningPreferences::defaults(Display::Show)
            ->withTheme('gore', Display::Hide, Severity::Moderate)
            ->withTheme('spiders', Display::Fold)
            ->withCategory('violence', Display::Fold);
        $stored = $preferences->toArray();

        self::assertSame([
            'default' => 'show',
            'themes' => ['gore' => ['display' => 'hide', 'at_least' => 'moderate'], 'spiders' => ['display' => 'fold']],
            'categories' => ['violence' => ['display' => 'fold']],
        ], $stored);
        self::assertEquals($preferences, WarningPreferences::fromArray($stored));
        self::assertEquals(WarningPreferences::defaults(Display::Hide), WarningPreferences::fromArray(['default' => 'hide']));
        self::assertEquals(WarningPreferences::defaults()->withTheme('a', Display::Hide), WarningPreferences::fromArray(['default' => 'fold', 'themes' => ['a' => ['display' => 'hide', 'at_least' => null]]]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'an unknown key' => [['default' => 'fold', 'lines' => []], 'preferences are an array with "default", "themes" and "categories"'];
        yield 'no default' => [[], 'the default display is "show", "fold" or "hide"'];
        yield 'a default that is no string' => [['default' => 1], 'the default display is "show", "fold" or "hide"'];
        yield 'an unknown default' => [['default' => 'blur'], 'the default display is "show", "fold" or "hide"'];
        yield 'themes not an array' => [['default' => 'fold', 'themes' => 'gore'], 'the preferences of themes and categories are arrays by key'];
        yield 'categories not an array' => [['default' => 'fold', 'categories' => 'gore'], 'the preferences of themes and categories are arrays by key'];
        yield 'a preference not an array' => [['default' => 'fold', 'themes' => ['gore' => 'hide']], 'the preference of "gore" is an array with "display" and an optional "at_least"'];
        yield 'a preference with an unknown key' => [['default' => 'fold', 'themes' => ['gore' => ['display' => 'hide', 'from' => 'mild']]], 'the preference of "gore" is an array'];
        yield 'no display' => [['default' => 'fold', 'themes' => ['gore' => []]], 'the preference of "gore" has an unknown display or severity'];
        yield 'a display that is no string' => [['default' => 'fold', 'themes' => ['gore' => ['display' => 2]]], 'the preference of "gore" has an unknown display or severity'];
        yield 'an unknown display' => [['default' => 'fold', 'themes' => ['gore' => ['display' => 'blur']]], 'the preference of "gore" has an unknown display or severity'];
        yield 'an unknown severity' => [['default' => 'fold', 'categories' => ['violence' => ['display' => 'hide', 'at_least' => 'extreme']]], 'the preference of "violence" has an unknown display or severity'];
        yield 'a severity that is no string' => [['default' => 'fold', 'categories' => ['violence' => ['display' => 'hide', 'at_least' => 2]]], 'the preference of "violence" has an unknown display or severity'];
        yield 'a malformed key' => [['default' => 'fold', 'themes' => ['Gore' => ['display' => 'hide']]], 'The stored content warnings cannot be read: Invalid content warning: "Gore" is not a theme or category key.'];
    }

    /**
     * @param array<array-key, mixed> $stored
     */
    #[DataProvider('unreadable')]
    public function testWhatToArrayDoesNotWriteIsUnreadable(array $stored, string $message): void
    {
        $this->expectException(UnreadableWarnings::class);
        $this->expectExceptionMessageIsOrContains($message);

        WarningPreferences::fromArray($stored);
    }

    public function testTooManyStoredEntriesAreUnreadable(): void
    {
        $themes = [];

        for ($index = 1; $index <= 2201; ++$index) {
            $themes['t'.$index] = ['display' => 'hide'];
        }

        $this->expectException(UnreadableWarnings::class);
        $this->expectExceptionMessageIsOrContains('preferences have at most 2200 entries');

        WarningPreferences::fromArray(['default' => 'fold', 'themes' => $themes]);
    }

    private function catalogue(): Catalogue
    {
        return new Catalogue(
            [new Category('violence', LocalizedText::of(['en' => 'Violence'])), new Category('phobias', LocalizedText::of(['en' => 'Phobias']))],
            [
                new Theme('gore', 'violence', LocalizedText::of(['en' => 'Gore'])),
                new Theme('war', 'violence', LocalizedText::of(['en' => 'War'])),
                new Theme('spiders', 'phobias', LocalizedText::of(['en' => 'Spiders'])),
            ],
        );
    }
}
