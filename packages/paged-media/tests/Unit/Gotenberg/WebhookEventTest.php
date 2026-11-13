<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\UnexpectedResponse;
use Phil\PagedMedia\Gotenberg\WebhookEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookEvent::class)]
final class WebhookEventTest extends TestCase
{
    public function testASuccessIsRead(): void
    {
        $event = WebhookEvent::fromJson('{"event":"webhook.success","correlationId":"my-request-42","timestamp":"2026-11-01T17:02:51.123456789Z"}');

        self::assertTrue($event->success);
        self::assertSame('my-request-42', $event->correlationId);
        self::assertSame('2026-11-01 17:02:51.123456', $event->timestamp?->format('Y-m-d H:i:s.u'));
        self::assertNull($event->errorStatus);
        self::assertNull($event->errorMessage);
    }

    public function testAnErrorKeepsTheStatusAndTheMessage(): void
    {
        $event = WebhookEvent::fromJson('{"event":"webhook.error","error":{"status":400,"message":"Invalid form data"}}');

        self::assertFalse($event->success);
        self::assertNull($event->correlationId);
        self::assertNull($event->timestamp);
        self::assertSame(400, $event->errorStatus);
        self::assertSame('Invalid form data', $event->errorMessage);
    }

    public function testASuccessCarriesNoError(): void
    {
        $event = WebhookEvent::fromJson('{"event":"webhook.success","error":{"status":400,"message":"stale"}}');

        self::assertNull($event->errorStatus);
        self::assertNull($event->errorMessage);
    }

    public function testAnotherEventIsRefused(): void
    {
        $this->expectException(UnexpectedResponse::class);
        $this->expectExceptionMessageIsOrContains('A Gotenberg webhook event is webhook.success or webhook.error; "webhook.started" is not.');

        WebhookEvent::fromJson('{"event":"webhook.started"}');
    }

    public function testATimestampThatIsNotADateIsRefused(): void
    {
        try {
            WebhookEvent::fromJson('{"event":"webhook.success","timestamp":"yesterday-ish"}');
            self::fail('A timestamp that is not a date was taken.');
        } catch (UnexpectedResponse $unexpectedResponse) {
            self::assertSame('A Gotenberg webhook event has a timestamp that is not a date: "yesterday-ish".', $unexpectedResponse->getMessage());
            self::assertNotNull($unexpectedResponse->getPrevious());
        }
    }
}
