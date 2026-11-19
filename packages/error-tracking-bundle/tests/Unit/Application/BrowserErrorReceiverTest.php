<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Application;

use Phil\ErrorTracking\Contract\CaptureThrottle;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\BrowserErrorParser;
use Phil\ErrorTrackingBundle\Application\BrowserErrorReceiver;
use Phil\ErrorTrackingBundle\Application\Receipt;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\ListedThrottle;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrowserErrorReceiver::class)]
#[CoversClass(Receipt::class)]
final class BrowserErrorReceiverTest extends TestCase
{
    private const string BODY = '{"type":"TypeError","message":"x of jane@example.com is undefined","url":"https://example.com/cart?coupon=SECRET","stack":"    at f (https://example.com/a.js:1:2)"}';

    private Tracking $tracking;

    protected function setUp(): void
    {
        $this->tracking = new Tracking();
    }

    public function testAReportBecomesAScrubbedEvent(): void
    {
        self::assertSame(Receipt::Accepted, $this->receiver()->receive('application/json; charset=utf-8', self::BODY, 'https://example.com', 'https://example.com', null, '192.0.2.123', 'Firefox/140'));

        $event = $this->tracking->store->onlyEvent();
        self::assertSame(Source::Browser, $event->source);
        self::assertSame(Severity::Error, $event->severity);
        self::assertSame('TypeError: x of [email] is undefined', $event->title());
        self::assertSame('https://example.com/a.js', $event->exceptions[0]->frames[0]->file);
        self::assertSame('GET', $event->request?->method);
        self::assertSame('https://example.com/cart', $event->request->url, 'the query of the page is never kept');
        self::assertNull($event->request->route);
        self::assertSame(['user-agent' => 'Firefox/140'], $event->request->headers);
        self::assertSame('192.0.2.0', $event->request->clientAddress);
        self::assertSame('1.0.0', $event->release);
    }

    public function testWithoutUserAgent(): void
    {
        self::assertSame(Receipt::Accepted, $this->receiver()->receive(' TEXT/PLAIN ; charset=utf-8', self::BODY, 'https://example.com', 'https://example.com', null, null, null));
        self::assertSame([], $this->tracking->store->onlyEvent()->request?->headers);
        self::assertNull($this->tracking->store->onlyEvent()->request->clientAddress);
    }

    #[TestWith(['application/xml'])]
    #[TestWith([''])]
    #[TestWith(['application/jsonx'])]
    public function testAnotherMediaTypeIsRefused(string $type): void
    {
        self::assertSame(Receipt::UnsupportedType, $this->receiver()->receive($type, self::BODY, 'https://example.com', 'https://example.com', null, null, null));
    }

    public function testATooLargeBodyIsRefused(): void
    {
        $receiver = $this->receiver(maxBytes: \strlen(self::BODY));

        self::assertSame(\strlen(self::BODY), $receiver->maxBytes());
        self::assertSame(Receipt::Accepted, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', null, null, null));
        self::assertSame(Receipt::TooLarge, $receiver->receive('application/json', self::BODY.' ', 'https://example.com', 'https://example.com', null, null, null));
        self::assertSame(16_384, BrowserErrorReceiver::DEFAULT_MAX_BYTES);
        self::assertSame(16_384, $this->receiver()->maxBytes());
    }

    public function testTheOriginIsChecked(): void
    {
        self::assertSame(Receipt::Forbidden, $this->receiver()->receive('application/json', self::BODY, null, 'https://example.com', null, null, null));
        self::assertSame(Receipt::Forbidden, $this->receiver()->receive('application/json', self::BODY, 'https://evil.example', 'https://example.com', null, null, null));
        self::assertSame(Receipt::Accepted, $this->receiver()->receive('application/json', self::BODY, 'HTTPS://EXAMPLE.COM', 'https://Example.com', null, null, null));

        $receiver = $this->receiver(origins: ['https://www.example.com']);
        self::assertSame(Receipt::Forbidden, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', null, null, null));
        self::assertSame(Receipt::Accepted, $receiver->receive('application/json', self::BODY, 'https://www.example.com', 'https://example.com', null, null, null));
    }

    public function testTheTokenIsChecked(): void
    {
        $receiver = $this->receiver(token: 's3cret');

        self::assertSame(Receipt::Forbidden, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', null, null, null));
        self::assertSame(Receipt::Forbidden, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', 'wrong', null, null));
        self::assertSame(Receipt::Accepted, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', 's3cret', null, null));
        self::assertSame(Receipt::Accepted, $this->receiver()->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', 'anything', null, null));
    }

    public function testTheClientIsThrottledByADigest(): void
    {
        $throttle = new ListedThrottle([hash_hmac('sha256', '192.0.2.9', 'secret'), hash_hmac('sha256', '', 'secret')]);
        $receiver = $this->receiver(throttle: $throttle);

        self::assertSame(Receipt::TooManyRequests, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', null, '192.0.2.9', null));
        self::assertSame(Receipt::TooManyRequests, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', null, null, null));
        self::assertSame(Receipt::Accepted, $receiver->receive('application/json', self::BODY, 'https://example.com', 'https://example.com', null, '192.0.2.10', null));
    }

    public function testAMalformedReportIsRefused(): void
    {
        self::assertSame(Receipt::Malformed, $this->receiver()->receive('application/json', '{"message":"m"}', 'https://example.com', 'https://example.com', null, null, null));
        self::assertSame([], $this->tracking->store->groups);
    }

    public function testTheStatuses(): void
    {
        self::assertSame([204, 400, 403, 413, 415, 429], array_map(static fn (Receipt $receipt): int => $receipt->value, Receipt::cases()));
    }

    /**
     * @param list<string> $origins
     */
    private function receiver(int $maxBytes = BrowserErrorReceiver::DEFAULT_MAX_BYTES, array $origins = [], ?string $token = null, ?CaptureThrottle $throttle = null): BrowserErrorReceiver
    {
        return new BrowserErrorReceiver(new BrowserErrorParser(), $this->tracking->factory, $this->tracking->capture, new Truncation(), 'secret', $maxBytes, $origins, $token, $throttle);
    }
}
