<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit;

use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\Exception\InvalidOption;
use Phil\LinkChecker\Extraction\AnchorIndex;
use Phil\LinkChecker\Extraction\LinkExtractor;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Memo;
use Phil\LinkChecker\Probe\ProbeResult;
use Phil\LinkChecker\Report\CheckedLink;
use Phil\LinkChecker\Report\LinkReport;
use Phil\LinkChecker\Tests\Support\MemoryInternalPages;
use Phil\LinkChecker\Tests\Support\ScriptedProbe;
use Phil\LinkChecker\UrlNormalizer;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

#[CoversClass(LinkChecker::class)]
#[CoversClass(Memo::class)]
#[CoversClass(AnchorIndex::class)]
#[CoversClass(InvalidOption::class)]
final class LinkCheckerTest extends TestCase
{
    private const string PAGE = 'https://site.example/blog/post';

    private function content(string $html, string $id = 'post-1', string $page = self::PAGE): ContentLinks
    {
        return new ContentLinks($id, Url::fromString($page), $html);
    }

    /**
     * @return list<array{string|null, string, int|null, string|null, string|null}> url, status, code, target, reason
     */
    private function rows(LinkReport $report): array
    {
        return array_map(static fn (CheckedLink $link): array => [
            $link->link->url?->toString(),
            $link->result->status->value,
            $link->result->code,
            $link->result->target?->toString(),
            $link->result->reason,
        ], $report->links);
    }

    public function testEachAddressIsCheckedOncePerBatch(): void
    {
        $probe = new ScriptedProbe(['https://example.com/gone' => new ProbeResult(new LinkResult(LinkStatus::Dead, 404))]);
        $checker = new LinkChecker($probe);

        $reports = iterator_to_array($checker->check([
            $this->content('<a href="https://example.com/gone">a</a><a href="https://EXAMPLE.com/gone#x">b</a><a href="https://example.com/ok">c</a>'),
            $this->content('<a href="https://example.com:443/gone">d</a>', 'post-2'),
        ]), false);

        self::assertCount(2, $reports);
        self::assertSame('post-1', $reports[0]->content);
        self::assertSame(self::PAGE, $reports[0]->page->toString());
        self::assertSame([
            ['https://example.com/gone', 'dead', 404, null, null],
            ['https://example.com/gone#x', 'dead', 404, null, null],
            ['https://example.com/ok', 'alive', 200, null, null],
        ], $this->rows($reports[0]));
        self::assertSame([['https://example.com/gone', 'dead', 404, null, null]], $this->rows($reports[1]));
        self::assertSame(['HEAD https://example.com/gone', 'HEAD https://example.com/ok'], $probe->asked());
    }

    public function testTheNormaliserDecidesWhichAddressesAreTheSame(): void
    {
        $html = '<a href="https://example.com/?a=1&b=2">a</a><a href="https://example.com/?b=2&a=1">b</a>';

        $plain = new ScriptedProbe();
        new LinkChecker($plain)->checkContent($this->content($html));
        self::assertCount(2, $plain->asked());

        $sorted = new ScriptedProbe();
        new LinkChecker($sorted, normalizer: new UrlNormalizer(sortQuery: true))->checkContent($this->content($html));
        self::assertSame(['HEAD https://example.com/?a=1&b=2'], $sorted->asked());
    }

    public function testAnotherBatchChecksAgainWithoutACache(): void
    {
        $probe = new ScriptedProbe();
        $checker = new LinkChecker($probe);

        $checker->checkContent($this->content('<a href="https://example.com/">a</a>'));
        $checker->checkContent($this->content('<a href="https://example.com/">a</a>'));

        self::assertCount(2, $probe->asked());
    }

    public function testLinksJudgedWithoutARequestAreNotProbed(): void
    {
        $probe = new ScriptedProbe();
        $report = new LinkChecker($probe)->checkContent($this->content('<a href="http://10.0.0.1/">ip</a><a href="http://[bad">bad</a>'));

        self::assertSame([
            [null, 'refused', null, null, 'not a public address'],
            [null, 'invalid', null, null, 'not a web address'],
        ], $this->rows($report));
        self::assertSame([], $probe->asked());
    }

    public function testAnAnchorOfTheContentItselfIsLookedForInIt(): void
    {
        $probe = new ScriptedProbe();
        $report = new LinkChecker($probe)->checkContent($this->content('<h2 id="intro">Intro</h2><a href="#intro">i</a><a href="#outro">o</a><a href="#top">t</a><a href="post">self</a><a href="https://site.example/blog/post?x=1#intro">other</a>'));

        self::assertSame([
            ['https://site.example/blog/post#intro', 'alive', null, null, null],
            ['https://site.example/blog/post#outro', 'missing-anchor', null, null, 'no element "outro" in the page'],
            ['https://site.example/blog/post#top', 'alive', null, null, null],
            ['https://site.example/blog/post', 'alive', null, null, null],
            ['https://site.example/blog/post?x=1#intro', 'alive', 200, null, null],
        ], $this->rows($report));
        self::assertSame(['HEAD https://site.example/blog/post?x=1'], $probe->asked());
    }

    public function testTheAnchorsOfEachContentAreItsOwn(): void
    {
        $reports = iterator_to_array(new LinkChecker(new ScriptedProbe())->check([
            $this->content('<p id="a">A</p><a href="#a">a</a>', 'one'),
            $this->content('<a href="#a">a</a>', 'two'),
        ]), false);

        self::assertSame('alive', $reports[0]->links[0]->result->status->value);
        self::assertSame('missing-anchor', $reports[1]->links[0]->result->status->value);
    }

    public function testAMissingAnchorIsCutInItsReason(): void
    {
        $fragment = str_repeat('x', 150);
        $report = new LinkChecker(new ScriptedProbe())->checkContent($this->content('<a href="#'.$fragment.'">x</a>'));

        self::assertSame('no element "'.str_repeat('x', 100).'" in the page', $report->links[0]->result->reason);
    }

    public function testAWrongHostIsCutInCharacters(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 100).'" is not a host name');

        new LinkChecker(new ScriptedProbe(), internalHosts: [str_repeat('é', 120)]);
    }

    public function testAnInternalPageTheApplicationGivesNeedsNoRequest(): void
    {
        $probe = new ScriptedProbe();
        $pages = new MemoryInternalPages(['https://site.example/about' => '<h2 id="team">Team</h2>']);
        $checker = new LinkChecker($probe, internalPages: $pages, internalHosts: ['site.example']);

        $report = $checker->checkContent($this->content('<a href="/about">a</a><a href="/about#team">t</a><a href="/about#gone">g</a><a href="/contact">c</a><a href="/contact#form">f</a>'));

        self::assertSame([
            ['https://site.example/about', 'alive', null, null, null],
            ['https://site.example/about#team', 'alive', null, null, null],
            ['https://site.example/about#gone', 'missing-anchor', null, null, 'no element "gone" in the page'],
            ['https://site.example/contact', 'alive', 200, null, null],
            ['https://site.example/contact#form', 'alive', 200, null, null],
        ], $this->rows($report));
        self::assertSame(['https://site.example/about', 'https://site.example/contact'], $pages->asked);
        self::assertSame(['HEAD https://site.example/contact', 'GET https://site.example/contact'], $probe->asked());
    }

    public function testAnInternalPageIsReadOverHttpWithoutTheApplication(): void
    {
        $probe = new ScriptedProbe([
            'https://site.example/about' => new ProbeResult(new LinkResult(LinkStatus::Alive, 200), '<h2 id="team">Team</h2>'),
        ]);
        $checker = new LinkChecker($probe, internalHosts: ['site.example', 'www.site.example']);

        $report = $checker->checkContent($this->content('<a href="/about#team">t</a><a href="/about#gone">g</a><a href="/about">a</a><a href="https://other.example/about#gone">o</a>'));

        self::assertSame([
            ['https://site.example/about#team', 'alive', 200, null, null],
            ['https://site.example/about#gone', 'missing-anchor', 200, null, 'no element "gone" in the page'],
            ['https://site.example/about', 'alive', 200, null, null],
            ['https://other.example/about#gone', 'alive', 200, null, null],
        ], $this->rows($report));
        self::assertSame(['GET https://site.example/about', 'HEAD https://site.example/about', 'HEAD https://other.example/about'], $probe->asked());
    }

    public function testTheAnchorOfARedirectedPageIsLookedForInItsTarget(): void
    {
        $target = Url::fromString('https://site.example/new');
        $probe = new ScriptedProbe([
            'https://site.example/old' => new ProbeResult(new LinkResult(LinkStatus::PermanentRedirect, 200, $target), '<p id="here">x</p>'),
            'https://site.example/moved' => new ProbeResult(new LinkResult(LinkStatus::TemporaryRedirect, 200, $target), '<p id="here">x</p>'),
        ]);
        $report = new LinkChecker($probe, internalHosts: ['site.example'])->checkContent($this->content('<a href="/old#here">h</a><a href="/old#gone">g</a><a href="/moved#gone">m</a>'));

        self::assertSame([
            ['https://site.example/old#here', 'permanent-redirect', 200, 'https://site.example/new', null],
            ['https://site.example/old#gone', 'missing-anchor', 200, 'https://site.example/new', 'no element "gone" in the page'],
            ['https://site.example/moved#gone', 'missing-anchor', 200, 'https://site.example/new', 'no element "gone" in the page'],
        ], $this->rows($report));
    }

    /**
     * @return iterable<string, array{ProbeResult, string}>
     */
    public static function pagesWithoutAnchors(): iterable
    {
        yield 'a dead page' => [new ProbeResult(new LinkResult(LinkStatus::Dead, 404), '<p>not found</p>'), 'dead'];
        yield 'a page to review' => [new ProbeResult(new LinkResult(LinkStatus::Review, 429), '<p>slow down</p>'), 'review'];
        yield 'an answer that is not HTML' => [new ProbeResult(new LinkResult(LinkStatus::Alive, 200)), 'alive'];
        yield 'a page read in part' => [new ProbeResult(new LinkResult(LinkStatus::Alive, 200), '<p>cut</p>', false), 'alive'];
    }

    #[DataProvider('pagesWithoutAnchors')]
    public function testTheAnchorIsOnlyLookedForInAPageFoundAndReadWhole(ProbeResult $answer, string $status): void
    {
        $report = new LinkChecker(new ScriptedProbe(['https://site.example/page' => $answer]), internalHosts: ['site.example'])->checkContent($this->content('<a href="/page#gone">g</a>'));

        self::assertSame($status, $report->links[0]->result->status->value);
        self::assertSame($answer->result->code, $report->links[0]->result->code);
    }

    public function testThePageOfAnInternalLinkIsReadAgainstTheBytesOfTheExtractor(): void
    {
        $probe = new ScriptedProbe(['https://site.example/page' => new ProbeResult(new LinkResult(LinkStatus::Alive, 200), str_repeat(' ', 100).'<p id="x">x</p>')]);
        $pages = new MemoryInternalPages(['https://site.example/known' => str_repeat(' ', 100).'<p id="x">x</p>']);
        $checker = new LinkChecker($probe, new LinkExtractor(maxBytes: 100), internalPages: $pages, internalHosts: ['site.example']);

        $report = $checker->checkContent($this->content('<a href="/page#y">p</a><a href="/known#y">k</a>'));

        // Past the bytes, the page counts as read in part: its anchors are not judged.
        self::assertSame(['alive', 'alive'], array_map(static fn (CheckedLink $link): string => $link->result->status->value, $report->links));

        $whole = new LinkChecker($probe, new LinkExtractor(maxBytes: 115), internalPages: $pages, internalHosts: ['site.example'])->checkContent($this->content('<a href="/page#y">p</a><a href="/known#y">k</a>'));
        self::assertSame(['missing-anchor', 'missing-anchor'], array_map(static fn (CheckedLink $link): string => $link->result->status->value, $whole->links));
    }

    public function testTheAnchorsOfAContentAreReadAgainstTheBytesOfTheExtractor(): void
    {
        $html = '<a href="#x">x</a>'.str_repeat(' ', 100);

        $report = new LinkChecker(new ScriptedProbe(), new LinkExtractor(maxBytes: 50))->checkContent($this->content($html));

        self::assertTrue($report->truncated);
        self::assertSame('alive', $report->links[0]->result->status->value);
    }

    public function testTheCacheKeepsTheResultsBetweenBatches(): void
    {
        $clock = new MockClock();
        $cache = new ArrayAdapter(clock: $clock);
        $probe = new ScriptedProbe([
            'https://example.com/busy' => new ProbeResult(new LinkResult(LinkStatus::Review, 429)),
            'https://example.com/old' => new ProbeResult(new LinkResult(LinkStatus::PermanentRedirect, 200, Url::fromString('https://example.com/new'), 'moved')),
        ]);
        $checker = new LinkChecker($probe, cache: $cache, ttl: 100, reviewTtl: 10);
        $html = '<a href="https://example.com/ok">ok</a><a href="https://example.com/busy">busy</a><a href="https://example.com/old">old</a>';

        $first = $checker->checkContent($this->content($html));
        $second = $checker->checkContent($this->content($html));

        self::assertEquals($first, $second);
        self::assertSame(['https://example.com/new', 'moved'], [$second->links[2]->result->target?->toString(), $second->links[2]->result->reason]);
        self::assertSame(['HEAD https://example.com/ok', 'HEAD https://example.com/busy', 'HEAD https://example.com/old'], $probe->asked());

        $clock->sleep(10);
        $checker->checkContent($this->content($html));
        self::assertSame(['HEAD https://example.com/busy'], $probe->asked());

        // Every entry has expired: the two kept 100 seconds, and the result to review kept again 10 seconds.
        $clock->sleep(90);
        $checker->checkContent($this->content($html));
        self::assertSame(['HEAD https://example.com/ok', 'HEAD https://example.com/busy', 'HEAD https://example.com/old'], $probe->asked());
    }

    public function testTheCacheNeverKeepsTheSiteItself(): void
    {
        $cache = new ArrayAdapter();
        $probe = new ScriptedProbe();
        $checker = new LinkChecker($probe, cache: $cache, internalHosts: ['site.example']);

        $checker->checkContent($this->content('<a href="/a">a</a><a href="/b#x">b</a>'));
        $checker->checkContent($this->content('<a href="/a">a</a><a href="/b#x">b</a>'));

        self::assertCount(4, $probe->asked());
        self::assertSame([], $cache->getValues());
    }

    public function testTheCacheKeyHidesTheAddress(): void
    {
        $cache = new ArrayAdapter();
        new LinkChecker(new ScriptedProbe(), cache: $cache)->checkContent($this->content('<a href="https://example.com/a#x">a</a>'));

        self::assertSame(['phil_link_checker.'.hash('xxh128', 'https://example.com/a')], array_keys($cache->getValues()));
    }

    public function testAnEntryOfAnotherShapeIsCheckedAgain(): void
    {
        $cache = new ArrayAdapter();
        $cache->get('phil_link_checker.'.hash('xxh128', 'https://example.com/a'), static fn (): string => 'other');

        $probe = new ScriptedProbe(['https://example.com/a' => new ProbeResult(new LinkResult(LinkStatus::Dead, 410))]);

        $report = new LinkChecker($probe, cache: $cache)->checkContent($this->content('<a href="https://example.com/a">a</a>'));

        self::assertSame('dead', $report->links[0]->result->status->value);
        self::assertSame(['HEAD https://example.com/a'], $probe->asked());
    }

    public function testATruncatedContentSaysSo(): void
    {
        $report = new LinkChecker(new ScriptedProbe(), new LinkExtractor(maxLinks: 1))->checkContent($this->content('<a href="https://example.com/1">1</a><a href="https://example.com/2">2</a>'));

        self::assertTrue($report->truncated);
        self::assertCount(1, $report->links);
    }

    public function testAContentWithoutLinksHasAnEmptyReport(): void
    {
        $report = new LinkChecker(new ScriptedProbe())->checkContent($this->content('<p>none</p>', 'empty'));

        self::assertSame('empty', $report->content);
        self::assertSame([], $report->links);
        self::assertFalse($report->truncated);
    }

    public function testAnEmptyBatchHasNoReport(): void
    {
        self::assertSame([], iterator_to_array(new LinkChecker(new ScriptedProbe())->check([])));
    }

    public function testTheBoundsOfTheLifetimes(): void
    {
        new LinkChecker(new ScriptedProbe(), ttl: 1, reviewTtl: 1);
        new LinkChecker(new ScriptedProbe(), ttl: LinkChecker::MAX_TTL, reviewTtl: LinkChecker::MAX_TTL);
        self::assertSame(2_592_000, LinkChecker::MAX_TTL);
    }

    /**
     * @return iterable<string, array{list<string>, int, int, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'an empty host' => [[''], 100, 10, '"" is not a host name in lowercase ASCII.'];
        yield 'a host in capitals' => [['Site.example'], 100, 10, '"Site.example" is not a host name in lowercase ASCII.'];
        yield 'a host with a port' => [['site.example:8080'], 100, 10, '"site.example:8080" is not a host name in lowercase ASCII.'];
        yield 'an international host' => [['bücher.example'], 100, 10, '"bücher.example" is not a host name in lowercase ASCII.'];
        yield 'a host with a line break' => [["site.example\n"], 100, 10, "\"site.example\n\" is not a host name in lowercase ASCII."];
        yield 'no lifetime' => [[], 0, 1, 'The lifetime of a result is 0; it must lie between 1 and 2592000.'];
        yield 'too long a lifetime' => [[], LinkChecker::MAX_TTL + 1, 1, 'The lifetime of a result is 2592001; it must lie between 1 and 2592000.'];
        yield 'no lifetime to review' => [[], 100, 0, 'The lifetime of a result to review is 0; it must lie between 1 and 100.'];
        yield 'a lifetime to review past the other' => [[], 100, 101, 'The lifetime of a result to review is 101; it must lie between 1 and 100.'];
    }

    /**
     * @param list<string> $hosts
     */
    #[DataProvider('invalidOptions')]
    public function testOptionsOutOfTheirBoundsAreRefused(array $hosts, int $ttl, int $reviewTtl, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        new LinkChecker(new ScriptedProbe(), internalHosts: $hosts, ttl: $ttl, reviewTtl: $reviewTtl);
    }

    public function testTheDefaultLifetimes(): void
    {
        $clock = new MockClock();
        $cache = new ArrayAdapter(clock: $clock);
        $probe = new ScriptedProbe(['https://example.com/busy' => new ProbeResult(new LinkResult(LinkStatus::Review, 503))]);
        $checker = new LinkChecker($probe, cache: $cache);
        $html = '<a href="https://example.com/ok">ok</a><a href="https://example.com/busy">busy</a>';

        $checker->checkContent($this->content($html));
        $clock->sleep(3_599);
        $checker->checkContent($this->content($html));
        self::assertSame(['HEAD https://example.com/ok', 'HEAD https://example.com/busy'], $probe->asked());

        $clock->sleep(1);
        $checker->checkContent($this->content($html));
        self::assertSame(['HEAD https://example.com/busy'], $probe->asked());

        $clock->sleep(86_400 - 3_600 - 1);
        $checker->checkContent($this->content($html));
        self::assertSame(['HEAD https://example.com/busy'], $probe->asked());

        // The live result expires after a day; the result to review was kept again a second ago.
        $clock->sleep(1);
        $checker->checkContent($this->content($html));
        self::assertSame(['HEAD https://example.com/ok'], $probe->asked());
    }
}
