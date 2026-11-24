<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Rule\DocumentLanguage;
use Phil\SeoCheck\Rule\DocumentTitle;
use Phil\SeoCheck\Rule\Excerpt;
use Phil\SeoCheck\Rule\LanguageTag;
use Phil\SeoCheck\Rule\MetaDescription;
use Phil\SeoCheck\Rule\SingleH1;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DocumentTitle::class)]
#[CoversClass(MetaDescription::class)]
#[CoversClass(DocumentLanguage::class)]
#[CoversClass(SingleH1::class)]
#[CoversClass(LanguageTag::class)]
#[CoversClass(Excerpt::class)]
final class HeadRulesTest extends RuleTestCase
{
    public function testACompliantPagePassesTheRulesOfItsHead(): void
    {
        $page = Pages::checked(Pages::html());

        foreach ([new DocumentTitle(), new MetaDescription(), new DocumentLanguage(), new SingleH1()] as $rule) {
            self::assertSame([], self::lines($rule, $page), $rule->name());
        }
    }

    public function testTheRulesHaveTheirNames(): void
    {
        self::assertSame('document-title', new DocumentTitle()->name());
        self::assertSame('meta-description', new MetaDescription()->name());
        self::assertSame('document-language', new DocumentLanguage()->name());
        self::assertSame('single-h1', new SingleH1()->name());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('titles')]
    public function testTheTitleIsPresentAndOfAReasonableLength(string $head, array $expected): void
    {
        self::assertSame($expected, self::lines(new DocumentTitle(), Pages::checked(Pages::html($head))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function titles(): iterable
    {
        yield 'missing' => ['', ['error document-title.missing']];
        yield 'empty' => ['<title>  </title>', ['error document-title.empty']];
        yield 'two' => ['<title>Une page bien nommée</title><title>Autre</title>', ['warning document-title.multiple (count=2)']];
        yield 'nine characters' => ['<title>Ouverture</title>', ['warning document-title.short (length=9, min=10) @ Ouverture']];
        yield 'ten characters' => ['<title>Ouvertures</title>', []];
        yield 'sixty characters' => ['<title>'.str_repeat('é', 60).'</title>', []];
        yield 'sixty-one characters' => ['<title>'.str_repeat('é', 61).'</title>', ['warning document-title.long (length=61, max=60) @ '.str_repeat('é', 61)]];
    }

    public function testTheTitleLengthsAreChosen(): void
    {
        $rule = new DocumentTitle(minLength: 3, maxLength: 5);

        self::assertSame(['warning document-title.short (length=2, min=3) @ Ab'], self::lines($rule, Pages::checked(Pages::html('<title>Ab</title>'))));
        self::assertSame([], self::lines($rule, Pages::checked(Pages::html('<title>Abcde</title>'))));
        self::assertSame(['warning document-title.long (length=6, max=5) @ Abcdef'], self::lines($rule, Pages::checked(Pages::html('<title>Abcdef</title>'))));
    }

    public function testALongTitleIsQuotedShort(): void
    {
        $title = str_repeat('a', 100);
        [$line] = self::lines(new DocumentTitle(), Pages::checked(Pages::html('<title>'.$title.'</title>')));

        self::assertSame('warning document-title.long (length=100, max=60) @ '.str_repeat('a', 79).'…', $line);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('descriptions')]
    public function testTheDescriptionIsPresentAndOfAReasonableLength(string $head, array $expected): void
    {
        self::assertSame($expected, self::lines(new MetaDescription(), Pages::checked(Pages::html('<title>Une page bien nommée</title>'.$head))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function descriptions(): iterable
    {
        $description = static fn (int $length): string => '<meta name="description" content="'.str_repeat('à', $length).'">';

        yield 'missing' => ['', ['warning meta-description.missing']];
        yield 'empty' => ['<meta name="description" content=" ">', ['warning meta-description.empty']];
        yield 'no content' => ['<meta name="description">', ['warning meta-description.missing']];
        yield 'named in capitals' => ['<meta name="Description" content="'.str_repeat('à', 80).'">', []];
        yield 'two' => [$description(80).$description(80), ['warning meta-description.multiple (count=2)']];
        yield 'forty-nine characters' => [$description(49), ['info meta-description.short (length=49, min=50) @ '.str_repeat('à', 49)]];
        yield 'fifty characters' => [$description(50), []];
        yield 'one hundred and sixty characters' => [$description(160), []];
        yield 'one hundred and sixty-one characters' => [$description(161), ['info meta-description.long (length=161, max=160) @ '.str_repeat('à', 79).'…']];
        yield 'spaces collapsed' => ['<meta name="description" content="'.str_repeat("à \n ", 20).'">', ['info meta-description.short (length=39, min=50) @ '.trim(str_repeat('à ', 20))]];
    }

    public function testTheDescriptionLengthsAreChosen(): void
    {
        $rule = new MetaDescription(minLength: 3, maxLength: 5);
        $page = static fn (string $content): CheckedPage => Pages::checked(Pages::html('<meta name="description" content="'.$content.'">'));

        self::assertSame(['info meta-description.short (length=2, min=3) @ ab'], self::lines($rule, $page('ab')));
        self::assertSame([], self::lines($rule, $page('abc')));
        self::assertSame([], self::lines($rule, $page('abcde')));
        self::assertSame(['info meta-description.long (length=6, max=5) @ abcdef'], self::lines($rule, $page('abcdef')));
    }

    /**
     * @param array<string, string> $headers
     * @param list<string>          $expected
     */
    #[DataProvider('languages')]
    public function testTheLanguageOfTheDocumentIsTheLanguagePublished(?string $lang, string $locale, array $headers, array $expected): void
    {
        $page = Pages::checked('', $locale, primary: Pages::rendered(Pages::html(lang: $lang), headers: $headers));

        self::assertSame($expected, self::lines(new DocumentLanguage(), $page));
    }

    /**
     * @return iterable<string, array{?string, string, array<string, string>, list<string>}>
     */
    public static function languages(): iterable
    {
        yield 'the same' => ['fr', 'fr', [], []];
        yield 'another case and an underscore' => ['FR-ca', 'fr_CA', [], []];
        yield 'missing' => [null, 'fr', [], ['error document-language.missing (locale=fr)']];
        yield 'empty' => [' ', 'fr', [], ['error document-language.missing (locale=fr)']];
        yield 'another region' => ['fr', 'fr-CA', [], ['warning document-language.region (lang=fr, locale=fr-CA)']];
        yield 'another language' => ['en', 'fr', [], ['error document-language.mismatch (lang=en, locale=fr)']];
        yield 'a header of the language' => ['fr', 'fr', ['Content-Language' => 'fr'], []];
        yield 'a header of the primary language' => ['fr-CA', 'fr-CA', ['Content-Language' => 'en, fr'], []];
        yield 'a header of the region' => ['fr-CA', 'fr-CA', ['content-language' => ' FR-ca '], []];
        yield 'a header of another language' => ['fr', 'fr', ['Content-Language' => 'en-US, de'], ['warning document-language.header (header=en-US, de, locale=fr)']];
        yield 'a header of another region' => ['fr-CA', 'fr-CA', ['Content-Language' => 'fr-FR'], ['warning document-language.header (header=fr-FR, locale=fr-CA)']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('headings')]
    public function testThePageHasOneFirstLevelHeading(string $body, array $expected): void
    {
        self::assertSame($expected, self::lines(new SingleH1(), Pages::checked(Pages::html(body: $body))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function headings(): iterable
    {
        yield 'one' => ['<h1>Ouverture</h1><h2>Horaires</h2>', []];
        yield 'none' => ['<h2>Horaires</h2>', ['warning single-h1.missing']];
        yield 'two' => ['<h1>Ouverture</h1><h1>Horaires</h1>', ['warning single-h1.multiple (count=2)']];
        yield 'empty' => ['<h1> <img src="/logo.png" alt="Logo"> </h1>', ['warning single-h1.empty']];
        yield 'two, one empty' => ['<h1>Ouverture</h1><h1></h1>', ['warning single-h1.multiple (count=2)', 'warning single-h1.empty']];
    }

    public function testLanguageTagsAreComparedWithoutCase(): void
    {
        self::assertSame('fr-ca', LanguageTag::normalize(' fr_CA '));
        self::assertSame('fr', LanguageTag::primary('FR-ca'));
        self::assertSame('zh', LanguageTag::primary('zh-Hant-TW'));
        self::assertTrue(LanguageTag::isValid('zh-Hant-TW'));
        self::assertTrue(LanguageTag::isValid('fr'));
        self::assertFalse(LanguageTag::isValid('fr_CA'));
        self::assertFalse(LanguageTag::isValid('f'));
        self::assertFalse(LanguageTag::isValid('fr-'));
        self::assertFalse(LanguageTag::isValid("fr\n"));
        self::assertTrue(LanguageTag::isValid('abc-'.implode('-', array_fill(0, 3, 'abcdefgh')).'-abcd'));
        self::assertFalse(LanguageTag::isValid('abc-'.implode('-', array_fill(0, 3, 'abcdefgh')).'-abcde'));
    }

    public function testAnExcerptIsOnOneLine(): void
    {
        self::assertSame('a b', Excerpt::of(" a \n b "));
        self::assertSame(str_repeat('x', 80), Excerpt::of(str_repeat('x', 80)));
        self::assertSame(str_repeat('x', 79).'…', Excerpt::of(str_repeat('x', 81)));
    }

    public function testAnExcerptIsCutFromItsStart(): void
    {
        self::assertSame('a'.str_repeat('b', Excerpt::LENGTH - 2).'…', Excerpt::of('a'.str_repeat('b', Excerpt::LENGTH + 10)));
    }
}
