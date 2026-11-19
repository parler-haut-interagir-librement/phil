<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Infrastructure;

use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Infrastructure\Collection\ConfiguredCollectionProjection;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Infrastructure\Analytics\AnalyticsVisitRecorder;
use Phil\ShortLinkBundle\Infrastructure\RateLimiter\RateLimiterCreationThrottle;
use Phil\ShortLinkBundle\Infrastructure\SafeHttp\SafeHttpTargetCheck;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(RateLimiterCreationThrottle::class)]
#[CoversClass(SafeHttpTargetCheck::class)]
#[CoversClass(AnalyticsVisitRecorder::class)]
final class AdaptersTest extends TestCase
{
    public function testTheAllowanceIsKeptPerAuthorUnderAHashedKey(): void
    {
        $storage = new InMemoryStorage();
        $throttle = new RateLimiterCreationThrottle(new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 hour'], $storage));

        self::assertTrue($throttle->consume('user:7'));
        self::assertTrue($throttle->consume('user:7'));
        self::assertFalse($throttle->consume('user:7'));
        self::assertTrue($throttle->consume('user:8'));
        self::assertNull($storage->fetch('test-user:7'), 'the author does not name the window');
        self::assertNotNull($storage->fetch('test-'.hash('sha256', 'phil_short_linkuser:7')));
    }

    public function testATargetThatAnswersIsAccepted(): void
    {
        $sent = [];
        $check = $this->check(static function (string $method) use (&$sent): MockResponse {
            $sent[] = $method;

            return new MockResponse('', ['http_code' => 204]);
        });

        self::assertNull($check->refusal(Url::fromString('https://example.com/')));
        self::assertSame(['HEAD'], $sent);
    }

    public function testAServerThatRefusesHeadIsAskedWithAGet(): void
    {
        $sent = [];
        $responses = [405 => 200, 501 => 404];

        foreach ($responses as $refusal => $answer) {
            $sent = [];
            $check = $this->check(static function (string $method) use (&$sent, $refusal, $answer): MockResponse {
                $sent[] = $method;

                return new MockResponse('ok', ['http_code' => 'HEAD' === $method ? $refusal : $answer, 'response_headers' => ['content-type' => 'text/html']]);
            });

            self::assertSame(404 === $answer ? 'it answers with the status 404' : null, $check->refusal(Url::fromString('https://example.com/')));
            self::assertSame(['HEAD', 'GET'], $sent);
        }
    }

    public function testAnErrorStatusIsARefusalAndARedirectIsNot(): void
    {
        self::assertSame('it answers with the status 400', $this->check(static fn (): MockResponse => new MockResponse('', ['http_code' => 400]))->refusal(Url::fromString('https://example.com/')));
        self::assertNull($this->check(static fn (): MockResponse => new MockResponse('', ['http_code' => 399]))->refusal(Url::fromString('https://example.com/')));
    }

    public function testAPrivateAddressATimeoutAndTooManyRedirectsAreRefusals(): void
    {
        self::assertSame('the address is not public', $this->check(static fn (): MockResponse => new MockResponse(''))->refusal(Url::fromString('https://internal.example/')));
        self::assertSame('it does not answer', $this->check(static fn (): MockResponse => new MockResponse('', ['error' => 'Connection refused']))->refusal(Url::fromString('https://example.com/')));
        self::assertSame('it redirects too many times', $this->check(static fn (): MockResponse => new MockResponse('', ['http_code' => 302, 'response_headers' => ['location' => 'https://example.com/again']]))->refusal(Url::fromString('https://example.com/')));
    }

    public function testAVisitCountsAsAServerEventWithItsCodeAndLocale(): void
    {
        $store = new class implements CounterStore {
            /** @var list<string> */
            public array $keys = [];

            public function increment(CounterKey $key): void
            {
                $this->keys[] = $key->metric.'|'.$key->dimension.'|'.$key->value;
            }

            public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array
            {
                return [];
            }

            public function deleteSite(SiteId $site): void
            {
            }
        };
        $site = ['origins' => ['https://example.com'], 'routes' => [], 'status' => 'active', 'goals' => [], 'forms' => [], 'click_names' => [], 'dimensions' => ['short_link' => ['abc123'], 'locale' => ['fr']], 'server_events' => ['short_link_visit', 'go']];
        $counter = new ServerEventCounter(new ConfiguredCollectionProjection(['main' => $site]), $store, new MockClock());

        new AnalyticsVisitRecorder($counter, 'main')->record(new ShortLinkVisit(1, 'abc123', 'go.example.com', 'fr'));
        new AnalyticsVisitRecorder($counter, 'main', 'go')->record(new ShortLinkVisit(1, 'abc123', 'go.example.com', null));

        foreach ([VisitorClass::Robot, VisitorClass::Prefetch, VisitorClass::LinkPreview] as $machine) {
            new AnalyticsVisitRecorder($counter, 'main')->record(new ShortLinkVisit(1, 'abc123', 'go.example.com', 'fr', $machine));
        }

        self::assertSame([
            'server_events|event|short_link_visit',
            'server_events.short_link_visit|short_link|abc123',
            'server_events.short_link_visit|locale|fr',
            'server_events|event|go',
            'server_events.go|short_link|abc123',
        ], $store->keys);
    }

    /**
     * @param callable(string, string, array<array-key, mixed>): MockResponse $responses
     */
    private function check(callable $responses): SafeHttpTargetCheck
    {
        return new SafeHttpTargetCheck(new SafeHttpClient(new MockHttpClient($responses), new Limits(), ['example.com' => '93.184.215.14', 'internal.example' => '10.0.0.7']));
    }
}
