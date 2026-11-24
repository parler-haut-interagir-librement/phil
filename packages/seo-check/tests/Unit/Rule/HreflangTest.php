<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\Markup\MarkupReader;
use Phil\SeoCheck\Rule\Hreflang;
use Phil\SeoCheck\Tests\Support\MemoryRenderedPages;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Hreflang::class)]
final class HreflangTest extends RuleTestCase
{
    private const string SELF = '<link rel="alternate" hreflang="fr" href="https://example.org/fr/news/opening">';

    public function testTheRuleHasItsName(): void
    {
        self::assertSame('hreflang', new Hreflang()->name());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('alternates')]
    public function testTheTranslationsAreValid(string $head, array $expected): void
    {
        self::assertSame($expected, self::lines(new Hreflang(), Pages::checked(Pages::html($head))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function alternates(): iterable
    {
        yield 'none' => ['', []];
        yield 'itself and a translation' => [self::SELF.'<link rel="alternate" hreflang="en-CA" href="https://example.org/en/news/opening">', []];
        yield 'x-default is optional and in any case' => [self::SELF.'<link rel="alternate" hreflang="X-Default" href="https://example.org/">', []];
        yield 'itself in another case' => ['<link rel="alternate" hreflang="FR" href="https://example.org/fr/news/opening">', []];
        yield 'an alternate without hreflang' => ['<link rel="alternate" href="https://example.org/feed.xml">', []];
        yield 'an invalid code' => [self::SELF.'<link rel="alternate" hreflang="en_CA" href="https://example.org/en/">', ['error hreflang.invalid-code (code=en_CA) @ https://example.org/en/']];
        yield 'a relative address' => [self::SELF.'<link rel="alternate" hreflang="en" href="/en/news/opening">', ['error hreflang.relative (code=en, href=/en/news/opening) @ /en/news/opening']];
        yield 'not an address' => [self::SELF.'<link rel="alternate" hreflang="en" href="ftp://example.org/en/">', ['error hreflang.invalid-url (code=en, href=ftp://example.org/en/) @ ftp://example.org/en/']];
        yield 'a language twice' => [
            self::SELF.str_repeat('<link rel="alternate" hreflang="en" href="https://example.org/en/a">', 2)
            .'<link rel="alternate" hreflang="EN" href="https://example.org/en/b"><link rel="alternate" hreflang="en" href="https://example.org/en/c">',
            ['error hreflang.duplicate (code=EN) @ EN'],
        ];
        yield 'without itself' => ['<link rel="alternate" hreflang="en" href="https://example.org/en/news/opening">', ['warning hreflang.missing-self (canonical=https://example.org/fr/news/opening)']];
        yield 'itself under another language' => ['<link rel="alternate" hreflang="en" href="https://example.org/fr/news/opening"><link rel="alternate" hreflang="x-default" href="https://example.org/fr/news/opening">', ['warning hreflang.self-language (codes=en, x-default, locale=fr)']];
    }

    public function testEachTranslationListsThePageBack(): void
    {
        $pages = new MemoryRenderedPages(others: [
            Pages::rendered(Pages::html('<link rel="alternate" hreflang="fr" href="https://example.org/fr/news/opening">'), 'https://example.org/en/news/opening'),
            Pages::rendered(Pages::html('<link rel="alternate" hreflang="fr" href="https://example.org/fr/">'), 'https://example.org/es/news/opening'),
            Pages::rendered(Pages::html('<link rel="alternate" hreflang="fr" href="/fr/news/opening">'), 'https://example.org/de/news/opening'),
        ]);
        $head = self::SELF
            .'<link rel="alternate" hreflang="en" href="https://example.org/en/news/opening">'
            .'<link rel="alternate" hreflang="es" href="https://example.org/es/news/opening">'
            .'<link rel="alternate" hreflang="de" href="https://example.org/de/news/opening">'
            .'<link rel="alternate" hreflang="it" href="https://example.org/it/news/opening">'
            .'<link rel="alternate" hreflang="x-default" href="https://example.org/">';

        self::assertSame(
            ['warning hreflang.not-reciprocal (code=es, href=https://example.org/es/news/opening) @ https://example.org/es/news/opening'],
            self::lines(new Hreflang($pages), Pages::checked(Pages::html($head))),
        );
        self::assertSame([
            'https://example.org/en/news/opening',
            'https://example.org/es/news/opening',
            'https://example.org/de/news/opening',
            'https://example.org/it/news/opening',
        ], $pages->asked);
    }

    public function testAtMostSoManyTranslationsAreRead(): void
    {
        $pages = new MemoryRenderedPages();
        $head = self::SELF
            .'<link rel="alternate" hreflang="en" href="https://example.org/en/news/opening">'
            .'<link rel="alternate" hreflang="es" href="https://example.org/es/news/opening">'
            .'<link rel="alternate" hreflang="de" href="https://example.org/de/news/opening">';

        self::lines(new Hreflang($pages, new MarkupReader(), 2), Pages::checked(Pages::html($head)));

        self::assertSame(['https://example.org/en/news/opening', 'https://example.org/es/news/opening'], $pages->asked);
    }

    public function testATranslationNotRenderedIsNotReported(): void
    {
        $head = self::SELF.'<link rel="alternate" hreflang="en" href="https://example.org/en/news/opening">';

        self::assertSame([], self::lines(new Hreflang(new MemoryRenderedPages()), Pages::checked(Pages::html($head))));
    }

    public function testAWrongTranslationDoesNotHideTheNextOnes(): void
    {
        $head = '<link rel="alternate" hreflang="not a code" href="https://example.org/x">'
            .'<link rel="alternate" hreflang="en" href="/en/news/opening">'
            .'<link rel="alternate" hreflang="de" href="http://localhost/de">'
            .self::SELF;

        self::assertSame([
            'error hreflang.invalid-code (code=not a code) @ https://example.org/x',
            'error hreflang.relative (code=en, href=/en/news/opening) @ /en/news/opening',
            'error hreflang.invalid-url (code=de, href=http://localhost/de) @ http://localhost/de',
        ], self::lines(new Hreflang(), Pages::checked(Pages::html($head))));
    }

    public function testTheFirstAddressOfALanguageIsTheOneRead(): void
    {
        $pages = new MemoryRenderedPages(others: [Pages::rendered(Pages::html(''), 'https://example.org/fr/other')]);
        $head = self::SELF.'<link rel="alternate" hreflang="fr" href="https://example.org/fr/other">';

        self::assertSame(['error hreflang.duplicate (code=fr) @ fr'], self::lines(new Hreflang($pages), Pages::checked(Pages::html($head))));
        self::assertSame([], $pages->asked);
    }

    public function testATranslationWhoseAddressesCannotBeReadDoesNotListThePageBack(): void
    {
        $pages = new MemoryRenderedPages(others: [Pages::rendered(Pages::html('<link rel="alternate" hreflang="fr" href="https://[bad">'), 'https://example.org/en/news/opening')]);
        $head = self::SELF.'<link rel="alternate" hreflang="en" href="https://example.org/en/news/opening">';

        self::assertSame(
            ['warning hreflang.not-reciprocal (code=en, href=https://example.org/en/news/opening) @ https://example.org/en/news/opening'],
            self::lines(new Hreflang($pages), Pages::checked(Pages::html($head))),
        );
    }
}
