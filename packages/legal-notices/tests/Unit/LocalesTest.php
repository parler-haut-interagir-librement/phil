<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit;

use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\Locales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locales::class)]
#[CoversClass(InvalidLegalNotice::class)]
final class LocalesTest extends TestCase
{
    public function testALocaleIsCheckedAndJoinedByUnderscores(): void
    {
        self::assertSame('fr', Locales::check('fr'));
        self::assertSame('fr_CA', Locales::check('fr-CA'));
        self::assertSame('zh_Hant_TW', Locales::check('zh_Hant_TW'));
        self::assertSame('sr_Latn_RS_2024', Locales::check('sr_Latn_RS_2024'));
        self::assertSame('fr', Locales::language('fr_CA'));
        self::assertSame('fr', Locales::language('fr'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLocales(): iterable
    {
        yield 'empty' => [''];
        yield 'capitals' => ['FR'];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'a subtag too short' => ['fr_C'];
        yield 'a subtag too long' => ['fr_ABCDEFGHI'];
        yield 'four subtags' => ['sr_Latn_RS_2024_x1'];
        yield 'a trailing line' => ["fr\n"];
        yield 'a prefix' => ['xfr_CA!'];
    }

    #[DataProvider('badLocales')]
    public function testABadLocaleIsRefused(string $locale): void
    {
        try {
            Locales::check($locale);
            self::fail('The locale was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame('locale', $invalidLegalNotice->reason);
            self::assertSame(\sprintf('"%s" is not a locale such as "en" or "fr_CA".', $locale), $invalidLegalNotice->getMessage());
        }
    }

    public function testALongLocaleIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 80).'" is not a locale');

        Locales::check(str_repeat('é', 90));
    }

    public function testAValueIsQuotedCut(): void
    {
        $invalid = InvalidLegalNotice::because('reason', 'Message.');

        self::assertSame(['reason', 'Message.', 0], [$invalid->reason, $invalid->getMessage(), $invalid->getCode()]);
        self::assertSame('"'.str_repeat('é', 80).'"', InvalidLegalNotice::quote(str_repeat('é', 81)));
    }

    public function testAValueIsFoundForALocaleItsLanguageOrAFallback(): void
    {
        $values = ['en' => 'one', 'fr' => 'un', 'fr_CA' => 'un (Canada)'];

        self::assertSame('un (Canada)', Locales::find($values, 'fr_CA'));
        self::assertSame('un', Locales::find($values, 'fr_BE'));
        self::assertNull(Locales::find($values, 'de'));
        self::assertSame('un', Locales::pick($values, 'fr_CH', 'en'));
        self::assertSame('one', Locales::pick($values, 'de', 'en_GB'));
        self::assertSame('un', Locales::pick(['it' => 'uno', 'fr' => 'un'], 'de', 'fr'));
        self::assertSame('uno', Locales::pick(['it' => 'uno', 'fr' => 'un'], 'de', 'es'));
    }

    public function testTextsAreTrimmedAndChecked(): void
    {
        self::assertSame(['fr_CA' => 'Bonjour', 'en' => 'Hello'], Locales::texts(['fr-CA' => "  Bonjour\n", 'en' => 'Hello'], 7, 'body'));
        self::assertSame([], Locales::texts([], 10, 'body'));
        self::assertCount(32, Locales::texts(self::locales(32), 10, 'body'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string, string}>
     */
    public static function badTexts(): iterable
    {
        yield 'too many locales' => [self::locales(33), 'too_many_locales', 'A body is given in 32 locales at most.'];
        yield 'an empty text' => [['fr' => ' '], 'empty_text', 'The body in "fr" is empty.'];
        yield 'not a text' => [['fr' => 3], 'empty_text', 'The body in "fr" is empty.'];
        yield 'too long' => [['fr' => str_repeat('a', 11)], 'invalid_text', 'The body in "fr" is UTF-8 text of 10 bytes at most.'];
        yield 'not UTF-8' => [['fr' => "\xC3"], 'invalid_text', 'The body in "fr" is UTF-8 text of 10 bytes at most.'];
        yield 'a bad locale' => [['FR' => 'a'], 'locale', '"FR" is not a locale such as "en" or "fr_CA".'];
        yield 'a numbered key' => [['a'], 'locale', '"0" is not a locale such as "en" or "fr_CA".'];
    }

    /**
     * @param array<array-key, mixed> $texts
     */
    #[DataProvider('badTexts')]
    public function testBadTextsAreRefused(array $texts, string $reason, string $message): void
    {
        try {
            Locales::texts($texts, 10, 'body');
            self::fail('The texts were accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame([$reason, $message], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }

    public function testATextAtTheLimitIsKept(): void
    {
        self::assertSame(['fr' => str_repeat('é', 5)], Locales::texts(['fr' => str_repeat('é', 5)], 10, 'body'));
    }

    /**
     * @return array<string, string>
     */
    private static function locales(int $count): array
    {
        $texts = [];

        for ($index = 0; $index < $count; ++$index) {
            $texts['fr_'.str_pad((string) $index, 3, '0', \STR_PAD_LEFT)] = 'a';
        }

        return $texts;
    }
}
