<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Exception\WebhookNotDelivered;
use Phil\NewsletterBundle\Application\Handler\DeliverAudienceWebhookHandler;
use Phil\NewsletterBundle\Application\Message\DeliverAudienceWebhook;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Infrastructure\Webhook\AudienceWebhookListener;
use Phil\NewsletterBundle\Infrastructure\Webhook\StandardWebhooksAudienceSender;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use Phil\NewsletterBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\StandardWebhooks\HmacSecret;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooksBundle\Application\SigningKeys;
use Phil\StandardWebhooksBundle\Infrastructure\Http\WebhookSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(AudienceWebhookListener::class)]
#[CoversClass(DeliverAudienceWebhookHandler::class)]
#[CoversClass(DeliverAudienceWebhook::class)]
#[CoversClass(StandardWebhooksAudienceSender::class)]
#[CoversClass(WebhookNotDelivered::class)]
final class AudienceWebhooksTest extends TestCase
{
    public function testEachReceiverGetsTheTypesItAskedFor(): void
    {
        $bus = new RecordingBus();
        $listener = new AudienceWebhookListener($bus, ['crm' => [], 'stats' => ['unsubscribed']]);
        $event = new AudienceChanged(AudienceEventType::Confirmed, 's1', 'default', 'l1', 'u1', new DateTimeImmutable('@1800000000'));

        $listener($event);
        $listener(new AudienceChanged(AudienceEventType::Unsubscribed, 's1', 'default', null, null, new DateTimeImmutable('@1800000001')));

        $messages = $bus->messages(DeliverAudienceWebhook::class);
        self::assertCount(3, $messages);
        self::assertEquals(new DeliverAudienceWebhook('crm', $event->id(), 'confirmed', 's1', 'default', 'l1', 'u1', 1_800_000_000), $messages[0]);
        self::assertSame(['crm', 'stats'], [$messages[1]->endpoint, $messages[2]->endpoint]);
    }

    public function testAReceiverGetsASignedEventWithoutTheAddress(): void
    {
        $newsletter = new Newsletter();
        $list = $newsletter->list();
        /** @var list<array{string, array<string, mixed>}> $requests */
        $requests = [];
        $handler = new DeliverAudienceWebhookHandler($this->sender(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$url, $options];

            return new MockResponse('', ['http_code' => 204]);
        }), $newsletter->lists, ['crm' => 'https://crm.example.com/hooks']);

        $handler(new DeliverAudienceWebhook('crm', str_repeat('a', 32), 'confirmed', 's1', 'default', $list->id, 'u1', 1_800_000_000));
        $handler(new DeliverAudienceWebhook('crm', str_repeat('b', 32), 'erased', 's1', 'default', null, null, 1_800_000_000));
        $handler(new DeliverAudienceWebhook('unknown', str_repeat('c', 32), 'erased', 's1', 'default', null, null, 1_800_000_000));

        self::assertCount(2, $requests);
        self::assertSame('https://crm.example.com/hooks', $requests[0][0]);
        self::assertSame('{"type":"newsletter.subscriber.confirmed","timestamp":"2027-01-15T08:00:00+00:00","data":{"subscriber":"s1","external_id":"u1","site":"default","list":"news"}}', $requests[0][1]['body']);
        self::assertSame('{"type":"newsletter.subscriber.erased","timestamp":"2027-01-15T08:00:00+00:00","data":{"subscriber":"s1","external_id":null,"site":"default","list":null}}', $requests[1][1]['body']);
        self::assertIsArray($requests[0][1]['headers']);
        self::assertContains('webhook-id: msg_'.str_repeat('a', 32).'_'.substr(hash('sha256', 'crm'), 0, 8), $requests[0][1]['headers']);
    }

    public function testARetryableFailureIsThrownAndAnotherLogged(): void
    {
        $newsletter = new Newsletter();
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->lines[] = (\is_string($level) ? $level : '').' '.$message.' '.json_encode($context);
            }
        };
        $message = new DeliverAudienceWebhook('crm', str_repeat('a', 32), 'confirmed', 's1', 'default', null, null, 1_800_000_000);

        new DeliverAudienceWebhookHandler($this->sender(new MockResponse('', ['http_code' => 404])), $newsletter->lists, ['crm' => 'https://crm.example.com/hooks'], $logger)($message);
        self::assertSame(['warning The audience webhook {event} to {endpoint} was refused. {"event":"'.str_repeat('a', 32).'","endpoint":"crm"}'], $logger->lines);
        new DeliverAudienceWebhookHandler($this->sender(new MockResponse('', ['http_code' => 404])), $newsletter->lists, ['crm' => 'https://crm.example.com/hooks'])($message);

        try {
            new DeliverAudienceWebhookHandler($this->sender(new MockResponse('', ['http_code' => 503])), $newsletter->lists, ['crm' => 'https://crm.example.com/hooks'])($message);
            self::fail('Expected a retryable failure.');
        } catch (WebhookNotDelivered $webhookNotDelivered) {
            self::assertTrue($webhookNotDelivered->retryable);
            self::assertSame('The receiver did not accept the audience webhook.', $webhookNotDelivered->getMessage());
            self::assertNotNull($webhookNotDelivered->getPrevious());
        }
    }

    private function sender(callable|MockResponse $response): StandardWebhooksAudienceSender
    {
        return new StandardWebhooksAudienceSender(new WebhookSender(new MockHttpClient($response), new Signer(), new SigningKeys(HmacSecret::generate()), new MockClock('2026-11-13 10:00:00 UTC'), allowPrivateNetworks: true));
    }

    public function testTheBodyKeepsItsSlashesAndItsLettersReadable(): void
    {
        $newsletter = new Newsletter();
        $body = '';
        $handler = new DeliverAudienceWebhookHandler($this->sender(static function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = \is_string($options['body'] ?? null) ? $options['body'] : '';

            return new MockResponse('', ['http_code' => 204]);
        }), $newsletter->lists, ['crm' => 'https://crm.example.com/hooks']);

        $handler(new DeliverAudienceWebhook('crm', str_repeat('a', 32), 'confirmed', 's1', 'défaut/x', null, 'é/1', 1_800_000_000));

        self::assertStringContainsString('"external_id":"é/1"', $body);
        self::assertStringContainsString('"site":"défaut/x"', $body);
    }

    public function testAnEndpointThatWantsOtherTypesLeavesTheNextOnesTheEvent(): void
    {
        $bus = new RecordingBus();
        $listener = new AudienceWebhookListener($bus, ['stats' => ['unsubscribed'], 'crm' => []]);

        $listener(new AudienceChanged(AudienceEventType::Confirmed, 's1', 'default', 'l1', 'u1', new DateTimeImmutable('@1800000000')));

        self::assertSame(['crm'], array_map(static fn (DeliverAudienceWebhook $message): string => $message->endpoint, $bus->messages(DeliverAudienceWebhook::class)));
    }
}
