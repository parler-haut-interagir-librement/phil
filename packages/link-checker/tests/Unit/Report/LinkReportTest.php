<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit\Report;

use Phil\LinkChecker\Extraction\Link;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Report\CheckedLink;
use Phil\LinkChecker\Report\LinkReport;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkReport::class)]
#[CoversClass(CheckedLink::class)]
#[CoversClass(Link::class)]
final class LinkReportTest extends TestCase
{
    private function checked(string $href, LinkResult $result, string $element = 'a', string $attribute = 'href', string $text = 'Text', ?LinkStatus $status = null): CheckedLink
    {
        $url = $status instanceof LinkStatus ? null : Url::fromString($href);

        return new CheckedLink(new Link($href, $url, $element, $attribute, $text, $status), $result);
    }

    private function report(bool $truncated = false): LinkReport
    {
        return new LinkReport('post-1', Url::fromString('https://site.example/post'), [
            $this->checked('https://example.com/ok', new LinkResult(LinkStatus::Alive, 200)),
            $this->checked('https://example.com/gone', new LinkResult(LinkStatus::Dead, 404), text: 'Gone'),
            $this->checked('https://example.com/old', new LinkResult(LinkStatus::PermanentRedirect, 200, Url::fromString('https://example.com/new')), 'img', 'src', ''),
            $this->checked('https://example.com/tmp', new LinkResult(LinkStatus::TemporaryRedirect, 200, Url::fromString('https://example.com/now'))),
            $this->checked('https://example.com/busy', new LinkResult(LinkStatus::Review, 429)),
            $this->checked('https://example.com/slow', new LinkResult(LinkStatus::Review, reason: 'timeout')),
            $this->checked('http://10.0.0.1/', new LinkResult(LinkStatus::Refused, reason: 'not a public address'), status: LinkStatus::Refused),
            $this->checked('https://site.example/about#team', new LinkResult(LinkStatus::MissingAnchor, 200, null, 'no element "team" in the page')),
            $this->checked('http://[bad', new LinkResult(LinkStatus::Invalid, reason: 'not a web address'), status: LinkStatus::Invalid),
        ], $truncated);
    }

    /**
     * @param list<CheckedLink> $links
     *
     * @return list<string>
     */
    private function hrefs(array $links): array
    {
        return array_map(static fn (CheckedLink $link): string => $link->link->href, $links);
    }

    public function testTheLinksAreSortedByWhatTheirCheckFound(): void
    {
        $report = $this->report();

        self::assertSame(['https://example.com/gone', 'https://site.example/about#team', 'http://[bad'], $this->hrefs($report->broken()));
        self::assertSame(['https://example.com/busy', 'https://example.com/slow'], $this->hrefs($report->toReview()));
        self::assertSame(['https://example.com/old', 'https://example.com/tmp'], $this->hrefs($report->redirected()));
        self::assertSame(['http://10.0.0.1/'], $this->hrefs($report->refused()));
        self::assertSame(['checked' => 9, 'broken' => 3, 'review' => 2, 'redirected' => 2, 'refused' => 1], $report->summary());
        self::assertFalse($report->isClean());
    }

    public function testAReportWithoutBrokenLinksIsClean(): void
    {
        $report = new LinkReport('post-2', Url::fromString('https://site.example/'), [
            $this->checked('https://example.com/busy', new LinkResult(LinkStatus::Review, 403)),
        ]);

        self::assertTrue($report->isClean());
        self::assertFalse($report->truncated);
    }

    public function testTheTextListsTheLinksThatAreNotAlive(): void
    {
        self::assertSame(<<<'TEXT'
            Links of post-1 (https://site.example/post): 9 checked, 3 broken, 2 to review, 2 redirected, 1 refused.
            - dead 404: https://example.com/gone (a href "Gone")
            - permanent-redirect 200: https://example.com/old (img src) -> https://example.com/new
            - temporary-redirect 200: https://example.com/tmp (a href "Text") -> https://example.com/now
            - review 429: https://example.com/busy (a href "Text")
            - review: https://example.com/slow (a href "Text"), timeout
            - refused: http://10.0.0.1/ (a href "Text"), not a public address
            - missing-anchor 200: https://site.example/about#team (a href "Text"), no element "team" in the page
            - invalid: http://[bad (a href "Text"), not a web address

            TEXT, $this->report()->toText());
    }

    public function testTheTextOfATruncatedReportSaysSo(): void
    {
        $report = new LinkReport('post-3', Url::fromString('https://site.example/'), [], true);

        self::assertSame("Links of post-3 (https://site.example/): 0 checked, 0 broken, 0 to review, 0 redirected, 0 refused.\nThe content was checked in part: it passes the bytes or the links the extractor reads.\n", $report->toText());
    }

    public function testTheJsonHoldsEveryLink(): void
    {
        $report = new LinkReport('post-é', Url::fromString('https://site.example/a/b'), [
            $this->checked('https://example.com/ok', new LinkResult(LinkStatus::Alive, 200), text: 'Café'),
            $this->checked('https://example.com/old', new LinkResult(LinkStatus::PermanentRedirect, 301, Url::fromString('https://example.com/new'), 'moved')),
            $this->checked('http://[bad', new LinkResult(LinkStatus::Invalid, reason: 'not a web address'), status: LinkStatus::Invalid),
        ], true);

        self::assertSame(
            '{"content":"post-é","page":"https://site.example/a/b","truncated":true,"summary":{"checked":3,"broken":1,"review":0,"redirected":1,"refused":0},"links":['
            .'{"url":"https://example.com/ok","href":"https://example.com/ok","element":"a","attribute":"href","text":"Café","status":"alive","code":200,"target":null,"reason":null},'
            .'{"url":"https://example.com/old","href":"https://example.com/old","element":"a","attribute":"href","text":"Text","status":"permanent-redirect","code":301,"target":"https://example.com/new","reason":"moved"},'
            .'{"url":null,"href":"http://[bad","element":"a","attribute":"href","text":"Text","status":"invalid","code":null,"target":null,"reason":"not a web address"}]}',
            $report->toJson(),
        );
    }

    public function testInvalidUtf8IsReplacedInTheJson(): void
    {
        $report = new LinkReport("post-\xFF", Url::fromString('https://site.example/'), []);

        self::assertStringStartsWith('{"content":"post-'."\u{FFFD}".'"', $report->toJson());
    }

    public function testALineNamesTheAddressResolvedRatherThanAsWritten(): void
    {
        $line = new CheckedLink(new Link('/a', Url::fromString('https://site.example/a'), 'a', 'href'), new LinkResult(LinkStatus::Dead, 404))->toText();

        self::assertSame('dead 404: https://site.example/a (a href)', $line);
    }

    public function testALinkKeepsWhatItIsGiven(): void
    {
        $link = new Link('/a', Url::fromString('https://site.example/a'), 'a', 'href');

        self::assertSame('', $link->text);
        self::assertNull($link->status);
        self::assertSame(200, Link::MAX_TEXT);
    }
}
