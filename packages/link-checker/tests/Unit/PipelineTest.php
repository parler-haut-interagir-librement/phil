<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit;

use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\LinkChecker\Report\CheckedLink;
use Phil\LinkChecker\Throttle\IntervalThrottle;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The whole chain, from the HTML of two contents to their reports, over phil/safe-http and a transport that answers
 * from a script: the extraction, the deduplication, the throttle on a mock clock, the cache, the redirects and the
 * anchors of an internal page read over HTTP.
 */
#[CoversNothing]
final class PipelineTest extends TestCase
{
    private const array RESOLVE = [
        'site.example' => '93.184.215.14',
        'example.com' => '93.184.215.15',
        'example.org' => '93.184.215.16',
        'private.example' => '192.168.1.10',
    ];

    public function testTwoContentsAreCheckedEndToEnd(): void
    {
        $requests = [];
        $answers = [
            'HEAD https://example.com/ok' => new MockResponse('', ['http_code' => 200]),
            'HEAD https://example.com/old' => new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://example.org/new']]),
            'HEAD https://example.org/new' => new MockResponse('', ['http_code' => 200]),
            'HEAD https://example.com/gone' => new MockResponse('', ['http_code' => 404]),
            'HEAD https://example.org/no-head' => new MockResponse('', ['http_code' => 405]),
            'GET https://example.org/no-head' => new MockResponse('', ['http_code' => 200]),
            'GET https://site.example/guide' => new MockResponse('<h2 id="install">Install</h2>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
        ];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests, $answers): MockResponse {
            $requests[] = $method.' '.$url;

            return $answers[$method.' '.$url] ?? new MockResponse('', ['http_code' => 500]);
        });
        $clock = new MockClock('2026-11-21 10:00:00');
        $probe = new HttpLinkProbe($client, resolve: self::RESOLVE, throttle: new IntervalThrottle(1.0, $clock));
        $checker = new LinkChecker($probe, cache: new ArrayAdapter(), internalHosts: ['site.example']);

        $reports = iterator_to_array($checker->check([
            new ContentLinks('1', Url::fromString('https://site.example/news/1'), '<a href="https://example.com/ok">ok</a><a href="https://example.com/old">old</a><a href="/guide#install">i</a><a href="/guide#remove">r</a><img src="https://example.com/gone" alt="Gone">'),
            new ContentLinks('2', Url::fromString('https://site.example/news/2'), '<a href="https://example.com/ok#again">ok</a><a href="https://example.org/no-head">nh</a><a href="https://private.example/">p</a><a href="mailto:a@example.com">m</a>'),
        ]), false);

        self::assertSame(['alive', 'permanent-redirect', 'alive', 'missing-anchor', 'dead'], array_map(static fn (CheckedLink $link): string => $link->result->status->value, $reports[0]->links));
        self::assertSame(['alive', 'alive', 'refused'], array_map(static fn (CheckedLink $link): string => $link->result->status->value, $reports[1]->links));
        self::assertSame('https://example.org/new', $reports[0]->links[1]->result->target?->toString());
        self::assertSame([
            'HEAD https://example.com/ok',
            'HEAD https://example.com/old',
            'HEAD https://example.org/new',
            'GET https://site.example/guide',
            'HEAD https://example.com/gone',
            'HEAD https://example.org/no-head',
            'GET https://example.org/no-head',
        ], $requests);
        // Three requests to example.com, a second apart — the redirect phil/safe-http follows is not paced —, then the
        // GET of example.org a second after its HEAD.
        self::assertSame('2026-11-21 10:00:03', $clock->now()->format('Y-m-d H:i:s'));
        self::assertFalse($reports[0]->isClean());
        self::assertTrue($reports[1]->isClean());
    }
}
