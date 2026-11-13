<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use Phil\ErrorTracking\RequestSummary;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestSummary::class)]
final class RequestSummaryTest extends TestCase
{
    public function testASummaryIsBounded(): void
    {
        $headers = [];

        for ($index = 0; $index < 60; ++$index) {
            $headers['X-Header-'.$index] = str_repeat('v', 2000);
        }

        $summary = new RequestSummary(str_repeat('get', 10), str_repeat('u', 3000), str_repeat('r', 300), $headers, str_repeat('1', 100));

        self::assertSame('GETGETGETGETGETG', $summary->method);
        self::assertSame(str_repeat('u', RequestSummary::MAX_URL_LENGTH), $summary->url);
        self::assertSame(str_repeat('r', 255), $summary->route);
        self::assertSame(str_repeat('1', 64), $summary->clientAddress);
        self::assertCount(RequestSummary::MAX_HEADERS, $summary->headers);
        self::assertSame(str_repeat('v', RequestSummary::MAX_HEADER_LENGTH), $summary->headers['x-header-49']);
        self::assertArrayNotHasKey('x-header-50', $summary->headers);
        self::assertSame([2048, 50, 1024], [RequestSummary::MAX_URL_LENGTH, RequestSummary::MAX_HEADERS, RequestSummary::MAX_HEADER_LENGTH]);
        self::assertSame(['x-'.str_repeat('n', 62) => 'v'], new RequestSummary('GET', '/', null, ['X-'.str_repeat('N', 80) => 'v'])->headers);
    }

    public function testTheDefaults(): void
    {
        $summary = new RequestSummary('post', '/orders');

        self::assertSame('POST', $summary->method);
        self::assertSame('/orders', $summary->url);
        self::assertNull($summary->route);
        self::assertSame([], $summary->headers);
        self::assertNull($summary->clientAddress);
    }

    public function testTheClientAddressIsAnonymized(): void
    {
        $summary = RequestSummary::anonymized('GET', '/a', 'home', ['Accept' => 'text/html'], '192.0.2.123', new Truncation());

        self::assertSame('192.0.2.0', $summary->clientAddress);
        self::assertSame('home', $summary->route);
        self::assertSame(['accept' => 'text/html'], $summary->headers);
        self::assertSame('GET', $summary->method);
        self::assertSame('/a', $summary->url);
        self::assertNull(RequestSummary::anonymized('GET', '/a', null, [], 'not an address', new Truncation())->clientAddress);
        self::assertNull(RequestSummary::anonymized('GET', '/a', null, [], null, new Truncation())->clientAddress);
    }
}
