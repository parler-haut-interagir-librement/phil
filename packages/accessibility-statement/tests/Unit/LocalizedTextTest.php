<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\AccessibilityStatement\LocalizedText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalizedText::class)]
#[CoversClass(InvalidStatement::class)]
final class LocalizedTextTest extends TestCase
{
    public function testATextIsFoundByLocaleThenPrimaryLanguageThenFallbackThenFirst(): void
    {
        $text = new LocalizedText(['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'fr-CA' => 'Québécois']);

        self::assertSame('Québécois', $text->in('fr-CA'));
        self::assertSame('Français', $text->in('fr-BE'));
        self::assertSame('English', $text->in('es', 'en'));
        self::assertSame('Français', $text->in('fr-BE', 'en'));
        self::assertSame('Deutsch', $text->in('es'));
        self::assertSame('Deutsch', $text->in('es', 'it'));
        self::assertTrue($text->has('fr-CA'));
        self::assertFalse($text->has('es'));
        self::assertSame(['de', 'en', 'fr', 'fr-CA'], $text->locales());
    }

    public function testOfGivesOneLanguage(): void
    {
        self::assertSame(['en' => 'Hello'], LocalizedText::of('Hello')->texts);
        self::assertSame(['fr' => 'Bonjour'], LocalizedText::of('Bonjour', 'fr')->texts);
    }

    public function testATextAtItsLimitsIsKept(): void
    {
        $texts = [];

        for ($i = 0; $i < LocalizedText::MAX_LOCALES; ++$i) {
            $texts['a'.\chr(97 + $i % 26).str_repeat('a', intdiv($i, 26))] = 'é';
        }

        self::assertCount(LocalizedText::MAX_LOCALES, new LocalizedText($texts)->texts);
        self::assertSame(LocalizedText::MAX_LENGTH, mb_strlen(new LocalizedText(['en' => str_repeat('é', LocalizedText::MAX_LENGTH)])->in('en')));
    }

    /**
     * @param array<array-key, mixed> $texts
     */
    #[DataProvider('invalidTexts')]
    public function testAnInvalidTextIsRefused(array $texts, string $reason): void
    {
        try {
            new LocalizedText($texts);
            self::fail('The text was accepted.');
        } catch (InvalidStatement $invalidStatement) {
            self::assertSame($reason, $invalidStatement->reason);
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidTexts(): iterable
    {
        $many = [];

        for ($i = 0; $i <= LocalizedText::MAX_LOCALES; ++$i) {
            $many['a'.\chr(97 + $i % 26).str_repeat('a', intdiv($i, 26))] = 'text';
        }

        yield 'no language' => [[], 'text_locales'];
        yield 'too many languages' => [$many, 'text_locales'];
        yield 'not a tag' => [['en_US!' => 'Hello'], 'text_locale'];
        yield 'a list' => [['Hello'], 'text_locale'];
        yield 'blank' => [['en' => " \n "], 'text_value'];
        yield 'empty' => [['en' => ''], 'text_value'];
        yield 'not a string' => [['en' => 42], 'text_value'];
        yield 'too long' => [['en' => str_repeat('é', LocalizedText::MAX_LENGTH + 1)], 'text_value'];
    }

    public function testTheMessageNamesTheLanguage(): void
    {
        $this->expectExceptionMessageIsOrContains('The text in "fr" is a string of 1 to 10000 characters, not blank.');

        new LocalizedText(['en' => 'ok', 'fr' => '']);
    }
}
