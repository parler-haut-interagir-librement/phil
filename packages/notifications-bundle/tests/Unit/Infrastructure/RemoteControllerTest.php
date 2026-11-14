<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Phil\NotificationsBundle\Infrastructure\Remote\RemoteNotificationController;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Processed;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingReceiver;
use Phil\StandardWebhooks\VerifiedWebhook;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(RemoteNotificationController::class)]
final class RemoteControllerTest extends TestCase
{
    private RecordingReceiver $receiver;

    private Processed $processed;

    protected function setUp(): void
    {
        $this->receiver = new RecordingReceiver();
        $this->processed = new Processed();
    }

    public function testANotificationOfATrustedOriginIsReceivedOnce(): void
    {
        $controller = $this->controller(['events'], true);
        $response = $controller($this->webhook('msg_1', $this->body()));

        self::assertSame(204, $response->getStatusCode());
        self::assertCount(1, $this->receiver->received);
        self::assertSame(['42'], $this->receiver->received[0]->recipients);
        self::assertSame(['msg_1' => '2026-11-08 12:00:00'], $this->processed->claims);

        self::assertSame(204, $controller($this->webhook('msg_1', $this->body()))->getStatusCode());
        self::assertCount(1, $this->receiver->received);
    }

    public function testWithoutAProcessedRepositoryEveryDeliveryIsReceived(): void
    {
        $controller = $this->controller(['events']);
        $controller($this->webhook('msg_1', $this->body()));
        $controller($this->webhook('msg_1', $this->body()));

        self::assertCount(2, $this->receiver->received);
    }

    public function testWhatThisSiteCannotTakeIsRefused(): void
    {
        foreach ([
            'This endpoint takes "notification.created" deliveries only.' => $this->body(type: 'invoice.paid'),
            'A remote notification names from 1 to 1000 recipients in a list.' => $this->body(['recipients' => []]),
            'The origin "shop" is not trusted.' => $this->body(['origin' => 'shop']),
            'The notification type "unknown" is not declared under "phil_notifications.types".' => $this->body(['notification' => 'unknown']),
        ] as $reason => $body) {
            $response = $this->controller(['events'], true)($this->webhook('msg_'.hash('sha256', $reason), $body));

            self::assertSame(422, $response->getStatusCode(), $reason);
            self::assertSame(['error' => $reason], json_decode((string) $response->getContent(), true));
        }

        self::assertSame([], $this->receiver->received);
        self::assertCount(1, $this->processed->claims, 'only the delivery refused for its type was claimed');
    }

    public function testNoOriginIsTrustedByDefault(): void
    {
        self::assertSame(422, new RemoteNotificationController($this->receiver, new MockClock())($this->webhook('msg_1', $this->body()))->getStatusCode());
    }

    public function testABodyThatIsNoEnvelopeIsABadRequest(): void
    {
        $response = $this->controller(['events'])($this->webhook('msg_1', '[1]'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'The webhook body is a JSON object.'], json_decode((string) $response->getContent(), true));
    }

    /**
     * @param list<string> $origins
     */
    private function controller(array $origins, bool $claims = false): RemoteNotificationController
    {
        return new RemoteNotificationController($this->receiver, new MockClock('2026-11-08 12:00:00 UTC'), $origins, $claims ? $this->processed : null);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function body(array $data = [], string $type = 'notification.created'): string
    {
        return json_encode(['type' => $type, 'data' => [...['origin' => 'events', 'notification' => 'comment.replied', 'recipients' => ['42']], ...$data]], \JSON_THROW_ON_ERROR);
    }

    private function webhook(string $id, string $body): VerifiedWebhook
    {
        return new VerifiedWebhook(WebhookId::fromString($id), WebhookTimestamp::fromHeader('1762603200'), $body);
    }
}
