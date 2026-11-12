<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\Adapter;

use Phil\CalendarSync\Adapter\HttpCall;
use Phil\CalendarSync\Adapter\Json;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\ProviderFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HttpCall::class)]
#[CoversClass(Json::class)]
final class HttpCallTest extends TestCase
{
    public function testARequestFollowsNoRedirectAndIsBounded(): void
    {
        $recorder = new Recorder(new MockResponse('ok'));
        $answer = new HttpCall($recorder->client, 'x', 4.0, 9.0)->send('GET', 'https://example.com/a');

        self::assertSame(200, $answer[0]);
        self::assertSame('ok', $answer[1]);
        self::assertSame(0, $recorder->options[0]['max_redirects']);
        self::assertSame(4.0, $recorder->options[0]['timeout']);
        self::assertSame(9.0, $recorder->options[0]['max_duration']);
        self::assertNull($recorder->options[0]['proxy']);
        self::assertSame('*', $recorder->options[0]['no_proxy']);
    }

    public function testAStatusOfThreeHundredIsNoAnswer(): void
    {
        $this->expectExceptionObject(ProviderFailed::status('x', 300));

        new HttpCall(new Recorder(new MockResponse('', ['http_code' => 300]))->client, 'x')->send('GET', 'https://example.com/a');
    }

    public function testTheMostNestedAnswerIsRead(): void
    {
        self::assertIsArray(Json::object(str_repeat('[', 63).str_repeat(']', 63)));

        $this->expectExceptionObject(InvalidCalendarData::because('An answer of a calendar service is JSON.'));
        Json::object(str_repeat('[', 64).str_repeat(']', 64));
    }

    public function testATimeIsWrittenWholeInRfc3339(): void
    {
        self::assertSame('2026-11-10T19:00:00+00:00', Json::moment('2026-11-10T19:00:00Z')->format('c'));

        foreach (['x2026-11-10T19:00:00Z', '2026-11-10T19:00:00Zx', '2026-11-10T19:00:00Z'."\n"] as $value) {
            try {
                Json::moment($value);
                self::fail('Read.');
            } catch (InvalidCalendarData $invalidCalendarData) {
                self::assertSame('A time of a calendar service is written in RFC 3339.', $invalidCalendarData->getMessage());
            }
        }
    }
}
