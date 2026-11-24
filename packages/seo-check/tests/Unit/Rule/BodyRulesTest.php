<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\Rule\CrawlableLinks;
use Phil\SeoCheck\Rule\GenericLinkTexts;
use Phil\SeoCheck\Rule\HttpStatus;
use Phil\SeoCheck\Rule\ImageAlt;
use Phil\SeoCheck\Rule\LinkText;
use Phil\SeoCheck\Rule\PageSize;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(HttpStatus::class)]
#[CoversClass(PageSize::class)]
#[CoversClass(CrawlableLinks::class)]
#[CoversClass(LinkText::class)]
#[CoversClass(GenericLinkTexts::class)]
#[CoversClass(ImageAlt::class)]
final class BodyRulesTest extends RuleTestCase
{
    public function testACompliantPagePassesTheRulesOfItsBody(): void
    {
        $page = Pages::checked(Pages::html());

        foreach ([new HttpStatus(), new PageSize(), new CrawlableLinks(), new LinkText(), new ImageAlt()] as $rule) {
            self::assertSame([], self::lines($rule, $page), $rule->name());
        }
    }

    public function testTheRulesHaveTheirNames(): void
    {
        self::assertSame('http-status', new HttpStatus()->name());
        self::assertSame('page-size', new PageSize()->name());
        self::assertSame('crawlable-links', new CrawlableLinks()->name());
        self::assertSame('link-text', new LinkText()->name());
        self::assertSame('image-alt', new ImageAlt()->name());
    }

    public function testEveryRenderingAnswersWithASuccess(): void
    {
        $page = Pages::checked('', others: [
            Pages::rendered('', 'https://example.org/fr/', 302),
            Pages::rendered('', 'https://example.org/fr/a', 299),
            Pages::rendered('', 'https://example.org/fr/b', 399),
            Pages::rendered('', 'https://example.org/fr/c', 400),
            Pages::rendered('', 'https://example.org/fr/d?x=1', 503),
        ], primary: Pages::rendered('', status: 301));

        self::assertSame([
            'warning http-status.redirect (status=301, path=/fr/news/opening) @ /fr/news/opening',
            'error http-status.unsuccessful (status=400, path=/fr/c) @ /fr/c',
            'error http-status.unsuccessful (status=503, path=/fr/d?x=1) @ /fr/d?x=1',
        ], self::lines(new HttpStatus(), $page));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('primaryStatuses')]
    public function testThePageAtItsAddressAnswersItself(int $status, array $expected): void
    {
        self::assertSame($expected, self::lines(new HttpStatus(), Pages::checked('', primary: Pages::rendered('', status: $status))));
    }

    /**
     * @return iterable<string, array{int, list<string>}>
     */
    public static function primaryStatuses(): iterable
    {
        yield '299' => [299, []];
        yield '300' => [300, ['warning http-status.redirect (status=300, path=/fr/news/opening) @ /fr/news/opening']];
        yield '399' => [399, ['warning http-status.redirect (status=399, path=/fr/news/opening) @ /fr/news/opening']];
        yield '400' => [400, ['error http-status.unsuccessful (status=400, path=/fr/news/opening) @ /fr/news/opening']];
    }

    public function testAPageLongerThanTheCheckReadsIsReported(): void
    {
        $html = Pages::html().str_repeat(' ', 5_000_000);

        self::assertSame(['warning page-size.truncated (bytes='.\strlen($html).')'], self::lines(new PageSize(), Pages::checked($html)));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('crawlableLinks')]
    public function testLinksCanBeFollowed(string $body, array $expected): void
    {
        self::assertSame($expected, self::lines(new CrawlableLinks(), Pages::checked(Pages::html(body: $body))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function crawlableLinks(): iterable
    {
        yield 'an address' => ['<a href="/fr/horaires">Les horaires</a>', []];
        yield 'an empty address' => ['<a href="">Cette page</a>', []];
        yield 'an anchor' => ['<a id="top"></a><a name="end"> </a>', []];
        yield 'no href' => ['<a onclick="go()">Les horaires</a>', ['warning crawlable-links.no-href (text=Les horaires) @ Les horaires']];
        yield 'a script' => ['<a href=" JavaScript:go()">Les horaires</a>', ['warning crawlable-links.javascript (href=JavaScript:go(), text=Les horaires) @ JavaScript:go()']];
        yield 'a script split by a tab' => ["<a href=\"java\tscr\nipt\r:go()\">Les horaires</a>", ['warning crawlable-links.javascript (href=java scr ipt :go(), text=Les horaires) @ java scr ipt :go()']];
        yield 'a word that starts like a script' => ['<a href="/javascript:go">Le guide</a>', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('linkTexts')]
    public function testLinkTextsSayWhereTheyLead(string $locale, string $body, array $expected): void
    {
        self::assertSame($expected, self::lines(new LinkText(), Pages::checked(Pages::html(body: $body), $locale)));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function linkTexts(): iterable
    {
        yield 'descriptive' => ['fr', '<a href="/fr/horaires">Les horaires de la boutique</a>', []];
        yield 'generic in French' => ['fr-CA', '<a href="/fr/horaires"> Cliquez  ICI ! </a>', ['warning link-text.generic (text=Cliquez ICI !, href=/fr/horaires) @ /fr/horaires']];
        yield 'with a typographic apostrophe' => ['fr', '<a href="/fr/a">Plus d’infos…</a>', ['warning link-text.generic (text=Plus d’infos…, href=/fr/a) @ /fr/a']];
        yield 'generic in English on a French page' => ['fr', '<a href="/en/">Click here</a>', []];
        yield 'generic in English' => ['en', '<a href="/en/">→ Read more</a>', ['warning link-text.generic (text=→ Read more, href=/en/) @ /en/']];
        yield 'no href' => ['fr', '<a>ici</a>', []];
        yield 'a language without a list' => ['nl', '<a href="/nl/">hier</a>', []];
    }

    public function testTheGenericTextsAreChosen(): void
    {
        $rule = new LinkText(['NL' => ['Klik HIER']]);

        self::assertSame(
            ['warning link-text.generic (text=klik hier, href=/nl/) @ /nl/'],
            self::lines($rule, Pages::checked(Pages::html(body: '<a href="/nl/">klik hier</a><a href="/nl/b">Lees verder</a>'), 'nl')),
        );
        self::assertSame([], self::lines($rule, Pages::checked(Pages::html(body: '<a href="/fr/">ici</a>'), 'fr')));
    }

    public function testATextIsComparedWithoutCaseSpacesNorPunctuationAround(): void
    {
        self::assertSame("plus d'infos", GenericLinkTexts::normalize(" « Plus  D’infos » …\n"));
        self::assertSame('a.b', GenericLinkTexts::normalize('a.b'));
        self::assertSame('', GenericLinkTexts::normalize(' → '));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('images')]
    public function testImagesHaveAnAlternative(string $body, array $expected): void
    {
        self::assertSame($expected, self::lines(new ImageAlt(), Pages::checked(Pages::html(body: $body))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function images(): iterable
    {
        yield 'described' => ['<img src="/a.jpg" alt="La vitrine">', []];
        yield 'decoration' => ['<img src="/a.jpg" alt="">', []];
        yield 'missing' => ['<img src="/a.jpg">', ['warning image-alt.missing (src=/a.jpg) @ /a.jpg']];
        yield 'missing, without src' => ['<img>', ['warning image-alt.missing (src=) @ ']];
        yield 'hidden by aria' => ['<img src="/a.jpg" aria-hidden=" TRUE ">', []];
        yield 'aria-hidden false' => ['<img src="/a.jpg" aria-hidden="false">', ['warning image-alt.missing (src=/a.jpg) @ /a.jpg']];
        yield 'a presentation' => ['<img src="/a.jpg" role="Presentation">', []];
        yield 'no role' => ['<img src="/a.jpg" role="img none">', []];
        yield 'another role' => ['<img src="/a.jpg" role="img">', ['warning image-alt.missing (src=/a.jpg) @ /a.jpg']];
    }

    public function testALinkWithoutAddressDoesNotHideTheNextOne(): void
    {
        $page = Pages::checked(Pages::html(body: '<h1>T</h1><a>Nowhere</a><a href="javascript:go()">Go</a>'));

        self::assertSame([
            'warning crawlable-links.no-href (text=Nowhere) @ Nowhere',
            'warning crawlable-links.javascript (href=javascript:go(), text=Go) @ javascript:go()',
        ], self::lines(new CrawlableLinks(), $page));
    }

    public function testAGenericTextIsComparedInLowerCaseWhateverItsAlphabet(): void
    {
        self::assertSame('más información', GenericLinkTexts::normalize('MÁS INFORMACIÓN'));
    }
}
