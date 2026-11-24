<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Bridge;

use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\SeoCheck\Bridge\LinkChecker\LinkCheckerStatuses;
use Phil\SeoCheck\Rule\BrokenLinks;
use Phil\SeoCheck\Tests\Support\Pages;
use Phil\SeoCheck\Tests\Support\ScriptedLinkProbe;
use Phil\SeoCheck\Tests\Unit\Rule\RuleTestCase;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LinkCheckerStatuses::class)]
final class LinkCheckerStatusesTest extends RuleTestCase
{
    public function testWhatTheCheckerFoundBecomesAStatusCode(): void
    {
        $probe = new ScriptedLinkProbe([
            'https://example.org/gone' => new LinkResult(LinkStatus::Dead, 410),
            'https://nowhere.example/' => new LinkResult(LinkStatus::Dead, reason: 'host not found'),
            'https://example.org/moved' => new LinkResult(LinkStatus::PermanentRedirect, 200, Url::fromString('https://example.org/new')),
            'https://example.org/later' => new LinkResult(LinkStatus::TemporaryRedirect, 200, Url::fromString('https://example.org/now')),
            'https://example.org/down' => new LinkResult(LinkStatus::Review, 503),
            'https://example.org/error' => new LinkResult(LinkStatus::Review, 500),
            'https://example.org/busy' => new LinkResult(LinkStatus::Review, 429),
            'https://example.org/slow' => new LinkResult(LinkStatus::Review, reason: 'timeout'),
            'https://example.org/private' => new LinkResult(LinkStatus::Refused, reason: 'not a public address'),
        ]);
        $links = array_map(Url::fromString(...), ['https://example.org/a?x=1&copy=2', 'https://example.org/gone', 'https://nowhere.example/', 'https://example.org/moved', 'https://example.org/later', 'https://example.org/down', 'https://example.org/error', 'https://example.org/busy', 'https://example.org/slow', 'https://example.org/private']);

        self::assertSame(
            ['https://example.org/a?x=1&copy=2' => 200, 'https://example.org/gone' => 410, 'https://nowhere.example/' => 404, 'https://example.org/moved' => 301, 'https://example.org/later' => 302, 'https://example.org/down' => 503, 'https://example.org/error' => 500],
            new LinkCheckerStatuses(new LinkChecker($probe))->statuses($links),
        );
        self::assertSame(array_map(static fn (Url $link): string => $link->toString(), $links), $probe->asked);
    }

    public function testNoLinkAsksNothing(): void
    {
        $probe = new ScriptedLinkProbe();

        self::assertSame([], new LinkCheckerStatuses(new LinkChecker($probe))->statuses([]));
        self::assertSame([], $probe->asked);
        self::assertSame('https://seo-check.invalid/', LinkCheckerStatuses::PAGE);
        self::assertSame('seo-check', LinkCheckerStatuses::CONTENT);
    }

    public function testTheBrokenLinksRuleReadsThem(): void
    {
        $probe = new ScriptedLinkProbe(['https://example.org/gone' => new LinkResult(LinkStatus::Dead, 404)]);
        $page = Pages::checked(Pages::html('<a href="/gone">Gone</a> <a href="https://example.org/fine">Fine</a>'));

        self::assertSame(['error broken-links.broken (href=https://example.org/gone, status=404) @ https://example.org/gone'], self::lines(new BrokenLinks(new LinkCheckerStatuses(new LinkChecker($probe))), $page));
    }
}
