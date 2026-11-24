<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\Markup\RobotsDirectives;
use Phil\SeoCheck\Rule\Indexing;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Indexing::class)]
final class IndexingTest extends RuleTestCase
{
    public function testTheRuleHasItsName(): void
    {
        self::assertSame('indexing', new Indexing()->name());
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param list<string>                       $expected
     */
    #[DataProvider('indexablePages')]
    public function testAnIndexablePageIsNotKeptOutOfTheIndex(string $head, array $headers, array $expected): void
    {
        $page = Pages::checked('', primary: Pages::rendered(Pages::html($head), headers: $headers));

        self::assertSame($expected, self::lines(new Indexing(), $page));
    }

    /**
     * @return iterable<string, array{string, array<string, string|list<string>>, list<string>}>
     */
    public static function indexablePages(): iterable
    {
        $at = ' @ /fr/news/opening';

        yield 'nothing said' => ['', [], []];
        yield 'index, follow' => ['<meta name="robots" content="index, follow">', ['X-Robots-Tag' => 'max-snippet: 20, unavailable_after: 2030-01-01'], []];
        yield 'noindex in a meta' => ['<meta name="robots" content="NOINDEX, follow">', [], ['error indexing.blocked (path=/fr/news/opening, directive=noindex, source=meta)'.$at]];
        yield 'none in a header' => ['', ['x-robots-tag' => 'none'], ['error indexing.blocked (path=/fr/news/opening, directive=none, source=header)'.$at]];
        yield 'noindex for one crawler in a meta' => ['<meta name="Googlebot" content="noindex">', [], ['warning indexing.blocked-for-agent (path=/fr/news/opening, directive=noindex, source=meta, agent=googlebot)'.$at]];
        yield 'noindex for one crawler in a header' => ['', ['X-Robots-Tag' => ['BingBot: nofollow, noindex']], ['warning indexing.blocked-for-agent (path=/fr/news/opening, directive=noindex, source=header, agent=bingbot)'.$at]];
        yield 'a meta of no crawler' => ['<meta name="viewport" content="noindex">', [], []];
        yield 'nofollow only' => ['<meta name="robots" content="nofollow">', [], []];
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param list<string>                       $expected
     */
    #[DataProvider('hiddenPages')]
    public function testAPageThatIsNotIndexableSaysNoindexToEveryCrawler(string $head, array $headers, array $expected): void
    {
        $page = Pages::checked('', indexable: false, primary: Pages::rendered(Pages::html($head), headers: $headers));

        self::assertSame($expected, self::lines(new Indexing(), $page));
    }

    /**
     * @return iterable<string, array{string, array<string, string|list<string>>, list<string>}>
     */
    public static function hiddenPages(): iterable
    {
        $missing = ['error indexing.not-blocked (path=/fr/news/opening) @ /fr/news/opening'];

        yield 'nothing said' => ['', [], $missing];
        yield 'index' => ['<meta name="robots" content="index">', [], $missing];
        yield 'noindex in a meta' => ['<meta name="robots" content="noindex">', [], []];
        yield 'noindex in a header' => ['', ['X-Robots-Tag' => 'noindex, nofollow'], []];
        yield 'none in a header' => ['', ['X-Robots-Tag' => 'none'], []];
        yield 'noindex for one crawler only' => ['<meta name="googlebot" content="noindex">', ['X-Robots-Tag' => 'otherbot: noindex'], $missing];
    }

    public function testEachChannelIsHeldToWhatTheApplicationSaysOfIt(): void
    {
        $open = Pages::html('<meta name="robots" content="index">');
        $closed = Pages::html('<meta name="robots" content="noindex">');
        $page = Pages::checked($open, others: [
            Pages::rendered($open, 'https://shop.example.org/fr/news/opening', channel: 'wholesale'),
            Pages::rendered($closed, 'https://www.example.org/fr/news/opening', channel: 'retail'),
            Pages::rendered($closed, 'https://kiosk.example.org/fr/news/opening', channel: 'kiosk'),
            Pages::rendered($closed, 'https://staff.example.org/fr/news/opening', channel: 'staff'),
        ], channels: ['wholesale' => false, 'retail' => true]);

        self::assertSame([
            'error indexing.not-blocked-in-channel (path=/fr/news/opening, channel=wholesale) @ /fr/news/opening',
            'error indexing.blocked-in-channel (path=/fr/news/opening, directive=noindex, source=meta, channel=retail) @ /fr/news/opening',
            'error indexing.blocked-in-channel (path=/fr/news/opening, directive=noindex, source=meta, channel=kiosk) @ /fr/news/opening',
            'error indexing.blocked-in-channel (path=/fr/news/opening, directive=noindex, source=meta, channel=staff) @ /fr/news/opening',
        ], self::lines(new Indexing(), $page));
    }

    public function testAChannelThatIsNotIndexableAcceptsNoindex(): void
    {
        $page = Pages::checked(Pages::html(), others: [
            Pages::rendered(Pages::html('<meta name="robots" content="noindex">'), 'https://shop.example.org/fr/news/opening', channel: 'wholesale'),
        ], channels: ['wholesale' => false]);

        self::assertSame([], self::lines(new Indexing(), $page));
    }

    public function testARenderingThatDoesNotAnswerWithASuccessIsLeftOut(): void
    {
        $noindex = Pages::html('<meta name="robots" content="noindex">');
        $page = Pages::checked('', others: [
            Pages::rendered($noindex, 'https://example.org/fr/', 302),
            Pages::rendered($noindex, 'https://example.org/fr/a', 199),
            Pages::rendered($noindex, 'https://example.org/fr/b', 299),
        ], primary: Pages::rendered($noindex, status: 404));

        self::assertSame(['error indexing.blocked (path=/fr/b, directive=noindex, source=meta) @ /fr/b'], self::lines(new Indexing(), $page));
    }

    public function testTheCrawlersReadAreChosen(): void
    {
        $page = Pages::checked(Pages::html('<meta name="googlebot" content="noindex"><meta name="ourbot" content="noindex">'));

        self::assertSame(
            ['warning indexing.blocked-for-agent (path=/fr/news/opening, directive=noindex, source=meta, agent=ourbot) @ /fr/news/opening'],
            self::lines(new Indexing(new RobotsDirectives(['OurBot'])), $page),
        );
    }

    public function testARedirectionOf300IsLeftOut(): void
    {
        self::assertSame([], self::lines(new Indexing(), Pages::checked('', primary: Pages::rendered(Pages::html('<meta name="robots" content="noindex">'), status: 300))));
    }
}
