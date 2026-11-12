<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\Collection;

use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Measure\ClickTracking;
use Phil\AnalyticsBundle\Domain\Measure\MeasureEvent;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Infrastructure\Collection\ConfiguredCollectionProjection;
use Phil\AnalyticsBundle\Infrastructure\Collection\NoOutboundLinks;
use Phil\AnalyticsBundle\Infrastructure\Collection\UnknownCountryResolver;
use Phil\AnalyticsBundle\Infrastructure\Collection\UserAgentCrawlerDetector;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredCollectionProjection::class)]
#[CoversClass(UserAgentCrawlerDetector::class)]
#[CoversClass(UnknownCountryResolver::class)]
#[CoversClass(NoOutboundLinks::class)]
final class DefaultAdaptersTest extends TestCase
{
    public function testTheProjectionFindsTheSiteThatDeclaresAnOrigin(): void
    {
        $projection = new ConfiguredCollectionProjection([
            'spring-con' => $this->site(['https://example.com', 'https://www.example.com'], ['home' => '/', 'talk' => '/talks/{slug}'], 'active', ['ticket' => ['route' => 'talk', 'event' => null, 'value' => null]], ['contact']),
            'autumn' => $this->site(['https://autumn.example'], [], 'suspended', ['sent' => ['route' => null, 'event' => 'form_submit', 'value' => 'contact']], []),
        ]);

        $site = $projection->siteDeclaring(Origin::fromString('https://www.example.com'));

        self::assertNotNull($site);
        self::assertSame('spring-con', $site->site->value);
        self::assertSame('talk', $site->routes->normalize('/talks/opening'));
        self::assertSame(SiteStatus::Active, $site->status);
        self::assertTrue($site->plan->forms->has('contact'));
        self::assertTrue($site->plan->goals[0]->isReachedByRoute('talk'));

        $autumn = $projection->siteDeclaring(Origin::fromString('https://autumn.example'));

        self::assertNotNull($autumn);
        self::assertSame(SiteStatus::Suspended, $autumn->status);
        self::assertSame('(other)', $autumn->routes->normalize('/'));
        self::assertTrue($autumn->plan->goals[0]->isReachedByEvent(MeasureEvent::FormSubmit, 'contact'));
        self::assertNull($projection->siteDeclaring(Origin::fromString('https://elsewhere.example')));
    }

    public function testTheProjectionReadsTheWholePlanAndFindsASiteByReference(): void
    {
        $projection = new ConfiguredCollectionProjection([
            'spring-con' => [
                ...$this->site(['https://example.com'], [], 'active', [], []),
                'clicks' => 'off',
                'downloads' => ['pdf'],
                'dimensions' => ['plan' => ['free', 'pro']],
                'server_events' => ['signup'],
            ],
            'autumn' => $this->site(['https://autumn.example'], [], 'active', [], []),
        ]);

        $site = $projection->site(SiteId::fromString('spring-con'));

        self::assertNotNull($site);
        self::assertSame($site, $projection->siteDeclaring(Origin::fromString('https://example.com')));
        self::assertSame(ClickTracking::Off, $site->plan->clicks);
        self::assertSame(['pdf'], $site->plan->downloads->toArray());
        self::assertSame('plan', $site->plan->dimensions[0]->name);
        self::assertSame(['free', 'pro'], $site->plan->dimensions[0]->values->toArray());
        self::assertSame(['signup'], $site->plan->serverEvents->toArray());

        $autumn = $projection->site(SiteId::fromString('autumn'));

        self::assertNotNull($autumn);
        self::assertSame(ClickTracking::Aggregate, $autumn->plan->clicks);
        self::assertTrue($autumn->plan->downloads->has('zip'));
        self::assertSame([], $autumn->plan->dimensions);
        self::assertSame([], $autumn->plan->serverEvents->toArray());
        self::assertNull($projection->site(SiteId::fromString('elsewhere')));
    }

    public function testAGoalWithoutRouteOrEventIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The goal "g" names a declared route, or a measured event and its value.');

        new ConfiguredCollectionProjection(['s' => $this->site(['https://example.com'], [], 'active', ['g' => ['route' => null, 'event' => 'nonsense', 'value' => 'x']], [])]);
    }

    public function testAnOriginDeclaredByTwoSitesIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The origin "https://example.com" is declared by two sites.');

        new ConfiguredCollectionProjection([
            'a' => self::site(['https://example.com'], [], 'active', [], []),
            'b' => self::site(['https://EXAMPLE.com:443'], [], 'active', [], []),
        ]);
    }

    public function testAnEventGoalWithoutValueIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new ConfiguredCollectionProjection(['s' => $this->site(['https://example.com'], [], 'active', ['g' => ['route' => null, 'event' => 'click', 'value' => null]], [])]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function userAgents(): iterable
    {
        yield 'Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', false];
        yield 'Safari on a phone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', false];
        yield 'a phone brand ending in bot' => ['Mozilla/5.0 (Linux; Android 13; CUBOT_X30) AppleWebKit/537.36', false];
        yield 'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true];
        yield 'a crawler' => ['SomeCrawler/1.0', true];
        yield 'a spider' => ['Baiduspider', true];
        yield 'curl' => ['curl/8.7.1', true];
        yield 'a headless browser' => ['Mozilla/5.0 HeadlessChrome/140.0', true];
        yield 'Python' => ['python-requests/2.32', true];
        yield 'empty' => ['', true];
        yield 'blank' => ['   ', true];
        yield 'a crawler word past the read length' => [str_repeat('a', 512).'bot', false];
    }

    #[DataProvider('userAgents')]
    public function testTheCrawlersThatSaySo(string $userAgent, bool $crawler): void
    {
        self::assertSame($crawler, new UserAgentCrawlerDetector()->isDeclaredCrawler($userAgent));
    }

    public function testTheCountryIsUnknownAndNoLinkIsDeclared(): void
    {
        self::assertFalse(new UnknownCountryResolver()->countryOf('192.0.2.1')->isKnown());
        self::assertNull(new NoOutboundLinks()->find(SiteId::fromString('s'), 'l1'));
    }

    /**
     * @param list<string>                                                         $origins
     * @param array<string, string>                                                $routes
     * @param array<string, array{route: ?string, event: ?string, value: ?string}> $goals
     * @param list<string>                                                         $forms
     *
     * @return array{origins: list<string>, routes: array<string, string>, status: string, goals: array<string, array{route: ?string, event: ?string, value: ?string}>, forms: list<string>, click_names: list<string>}
     */
    private function site(array $origins, array $routes, string $status, array $goals, array $forms): array
    {
        return ['origins' => $origins, 'routes' => $routes, 'status' => $status, 'goals' => $goals, 'forms' => $forms, 'click_names' => []];
    }
}
