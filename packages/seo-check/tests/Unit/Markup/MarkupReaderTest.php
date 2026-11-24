<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Markup;

use Phil\SeoCheck\Markup\Alternate;
use Phil\SeoCheck\Markup\Image;
use Phil\SeoCheck\Markup\Link;
use Phil\SeoCheck\Markup\MarkupReader;
use Phil\SeoCheck\Markup\PageMarkup;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkupReader::class)]
#[CoversClass(PageMarkup::class)]
#[CoversClass(Link::class)]
#[CoversClass(Image::class)]
#[CoversClass(Alternate::class)]
final class MarkupReaderTest extends TestCase
{
    public function testItReadsWhatTheRulesNeed(): void
    {
        $markup = new MarkupReader()->read(Pages::rendered(Pages::html(
            '<title> Une  page </title><meta name=" ROBOTS " content="noindex"><meta name="robots" content="nofollow"><meta content="orphan"><meta name="empty">'
            .'<link rel="Canonical Alternate" hreflang=" fr " href="https://example.org/fr/"><link rel="alternate" hreflang="en"><link rel="canonical" href="/b"><link rel="alternate" href="/feed">',
            "<h1>Un\n titre</h1><a href=\"/a\"> Les\t horaires </a><a>Sans</a><img src=\"/i.png\" alt=\"\"><img role=\"none\"><img aria-hidden=\"true\" src=\"/j.png\">",
            ' fr-CA ',
        )));

        self::assertSame(['Une page'], $markup->titles);
        self::assertSame(['robots' => ['noindex', 'nofollow']], $markup->meta);
        self::assertSame(['noindex', 'nofollow'], $markup->meta('Robots'));
        self::assertSame([], $markup->meta('description'));
        self::assertSame('fr-CA', $markup->language);
        self::assertSame(['Un titre'], $markup->headings);
        self::assertSame(['https://example.org/fr/', '/b'], $markup->canonicals);
        self::assertEquals([new Alternate('fr', 'https://example.org/fr/')], $markup->alternates);
        self::assertEquals([new Link('/a', 'Les horaires'), new Link(null, 'Sans')], $markup->links);
        self::assertEquals([new Image('/i.png', ''), new Image(null, null, true), new Image('/j.png', null, true)], $markup->images);
        self::assertFalse($markup->truncated);
    }

    public function testADocumentWithoutLangHasNoLanguage(): void
    {
        self::assertNull(new MarkupReader()->read(Pages::rendered(Pages::html(lang: null)))->language);
    }

    public function testWhatIsNotHtmlGivesEmptyMarkup(): void
    {
        $markup = new MarkupReader()->read(Pages::rendered("\x00\xff{\"json\": true}"));

        self::assertSame([], $markup->titles);
        self::assertSame([], $markup->links);
        self::assertNull($markup->language);
    }

    public function testThePageIsCutAtItsLimit(): void
    {
        $html = '<html><head><title>Avant</title></head><body><a href="/a">A</a>'.str_repeat(' ', 100).'<a href="/b">B</a></body></html>';
        $markup = new MarkupReader(maxBytes: 120)->read(Pages::rendered($html));

        self::assertTrue($markup->truncated);
        self::assertEquals([new Link('/a', 'A')], $markup->links);
        self::assertFalse(new MarkupReader(maxBytes: \strlen($html))->read(Pages::rendered($html))->truncated);
        self::assertTrue(new MarkupReader(maxBytes: \strlen($html) - 1)->read(Pages::rendered($html))->truncated);
    }

    public function testAtMostSoManyElementsOfAKindAreRead(): void
    {
        $markup = new MarkupReader(maxElements: 2)->read(Pages::rendered(Pages::html(body: str_repeat('<a href="/a">A</a><img alt="">', 3))));

        self::assertCount(2, $markup->links);
        self::assertCount(2, $markup->images);
    }

    public function testTheCharacterSetOfTheResponseIsRead(): void
    {
        $html = '<html><head><title>'."\xE9t\xE9".'</title></head></html>';

        self::assertSame(['été'], new MarkupReader()->read(Pages::rendered($html, headers: ['Content-Type' => 'text/html; charset="ISO-8859-1"']))->titles);
        self::assertSame(['été'], new MarkupReader()->read(Pages::rendered($html, headers: ['Content-Type' => ['text/plain', 'text/html;charset=windows-1252']]))->titles);
        self::assertSame(["\u{FFFD}t\u{FFFD}"], new MarkupReader()->read(Pages::rendered($html, headers: ['Content-Type' => 'text/html; charset=nothing-known']))->titles);
    }

    public function testATextIsOnOneLineAndCutShort(): void
    {
        self::assertSame('a b', MarkupReader::text(" a \n\t b "));
        self::assertSame('', MarkupReader::text(null));
        self::assertSame(1_000, mb_strlen(MarkupReader::text(str_repeat('é', 1_001))));
    }

    public function testTokensAreSplitOnWhitespace(): void
    {
        self::assertSame(['canonical', 'alternate'], MarkupReader::tokens(" Canonical\t\n\f\rALTERNATE "));
        self::assertSame([], MarkupReader::tokens(''));
    }

    public function testAPageCutAtItsLimitIsReadFromItsFirstByte(): void
    {
        $markup = new MarkupReader(maxBytes: 40)->read(Pages::rendered('<title>Avant</title>'.str_repeat(' ', 100)));

        self::assertTrue($markup->truncated);
        self::assertSame(['Avant'], $markup->titles);
    }

    public function testAnImageHiddenInCapitalsOrWithSpacesIsHidden(): void
    {
        $markup = new MarkupReader()->read(Pages::rendered(Pages::html(body: '<img src="/a.png" aria-hidden=" TRUE ">')));

        self::assertTrue($markup->images[0]->hidden);
    }

    public function testTheCharsetOfTheHeaderIsReadInAnyCase(): void
    {
        $markup = new MarkupReader()->read(Pages::rendered("<title>\xE9t\xE9</title>", headers: ['Content-Type' => 'text/html; CHARSET=ISO-8859-1']));

        self::assertSame(['été'], $markup->titles);
    }

    public function testMarkupIsWholeByDefault(): void
    {
        self::assertFalse(new PageMarkup()->truncated);
    }
}
