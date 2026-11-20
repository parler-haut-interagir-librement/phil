<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure\WebPush;

use DateTimeImmutable;
use Override;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Phil\NotificationsBundle\Application\WebPushMessage;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\WebPush\MinishlinkWebPushSender;
use Phil\NotificationsBundle\Infrastructure\WebPush\VapidKeys;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Logs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(MinishlinkWebPushSender::class)]
final class MinishlinkWebPushSenderTest extends TestCase
{
    private InMemoryPushSubscriptions $subscriptions;

    private VapidKeys $vapid;

    /** @var list<array{string, string, array<mixed>}> */
    private array $requests = [];

    #[Override]
    protected function setUp(): void
    {
        $keys = VapidKeys::generate();
        $this->vapid = new VapidKeys('mailto:ops@example.com', $keys['publicKey'], $keys['privateKey']);
        $this->subscriptions = new InMemoryPushSubscriptions();
    }

    public function testEachBrowserOfTheRecipientGetsTheMessageEncryptedAndSigned(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/fcm/send/a'));
        $this->subscriptions->subscribe('42', Browser::subscription('https://updates.push.services.mozilla.com/wpush/v2/b', PushSubscription::AESGCM));
        $this->subscriptions->subscribe('43', Browser::subscription('https://web.push.apple.com/c'));

        $this->sender([new MockResponse('', ['http_code' => 201]), new MockResponse('', ['http_code' => 201])], ttl: 600, timeout: 5.0)->send('42', $this->rendered());

        self::assertCount(2, $this->requests);
        [$method, $url, $options] = $this->requests[0];
        $headers = $this->headers($options);

        self::assertSame('POST', $method);
        self::assertSame('https://updates.push.services.mozilla.com/wpush/v2/b', $url);
        self::assertSame('https://fcm.googleapis.com/fcm/send/a', $this->requests[1][1]);
        self::assertSame('600', $headers['ttl']);
        self::assertSame('high', $headers['urgency']);
        self::assertSame(WebPushMessage::of($this->rendered(), $this->type())->topic, $headers['topic']);
        self::assertSame('aesgcm', $headers['content-encoding']);
        self::assertSame('aes128gcm', $this->headers($this->requests[1][2])['content-encoding']);
        self::assertStringStartsWith('vapid t=', $this->headers($this->requests[1][2])['authorization']);
        self::assertStringContainsString(', k='.$this->vapid->publicKey, $this->headers($this->requests[1][2])['authorization']);
        self::assertSame(0, $options['max_redirects']);
        self::assertEqualsWithDelta(5.0, $options['timeout'], 0.0001);

        $body = $this->requests[1][2]['body'];
        self::assertIsString($body);
        self::assertStringNotContainsString('A reply', $body);
        self::assertGreaterThan(\strlen(WebPushMessage::of($this->rendered(), $this->type())->payload), \strlen($body));
    }

    public function testARecipientWithoutBrowserDoesNotEvenLookAtTheTypeOfTheNotification(): void
    {
        $unknown = new RenderedNotification(new Notification('n1', 'ghost.type', '42', null, new DateTimeImmutable('2026-11-08 10:00 UTC')), 'A title', 'A body');

        self::assertSame(ChannelOutcome::Unreachable, $this->sender([])->send('42', $unknown));
        self::assertSame([], $this->requests);
    }

    public function testARecipientWithoutBrowserGetsNothing(): void
    {
        self::assertSame(ChannelOutcome::Unreachable, $this->sender([])->send('42', $this->rendered()));

        self::assertSame([], $this->requests);
    }

    public function testAnEndpointItsPushServiceNoLongerKnowsIsForgotten(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/b'));
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/c'));

        $logs = new Logs();

        $outcome = $this->sender([new MockResponse('', ['http_code' => 410]), new MockResponse('', ['http_code' => 404]), new MockResponse('', ['http_code' => 201])], $logs)->send('42', $this->rendered());

        self::assertSame(ChannelOutcome::Accepted, $outcome);

        self::assertSame(['https://fcm.googleapis.com/a'], array_map(static fn (PushSubscription $s): string => $s->endpoint, $this->subscriptions->of('42')));
        self::assertSame([], array_values(array_filter($logs->records, static fn (string $record): bool => str_starts_with($record, 'warning'))));
    }

    public function testAnyOtherRefusalIsLoggedWithTheHostOnly(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/fcm/send/secret-token'));
        $this->subscriptions->subscribe('42', Browser::subscription('https://web.push.apple.com/other-token'));

        $logs = new Logs();

        $outcome = $this->sender([new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://10.0.0.1/']]), new MockResponse('', ['http_code' => 429])], $logs)->send('42', $this->rendered());

        self::assertSame(ChannelOutcome::Refused, $outcome);

        self::assertCount(2, $this->requests);
        self::assertCount(2, $this->subscriptions->of('42'));
        self::assertContains('warning: The push service web.push.apple.com refused a notification of type comment.replied: 301.', $logs->records);
        self::assertContains('warning: The push service fcm.googleapis.com refused a notification of type comment.replied: 429.', $logs->records);
        self::assertStringNotContainsString('token', implode("\n", $logs->records));
    }

    public function testAPushServiceThatDoesNotAnswerIsLogged(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $logs = new Logs();

        self::assertSame(ChannelOutcome::Refused, $this->sender([new MockResponse('', ['error' => 'Connection refused'])], $logs)->send('42', $this->rendered()));

        self::assertCount(1, $this->subscriptions->of('42'));
        self::assertContains('warning: The push service fcm.googleapis.com refused a notification of type comment.replied: no answer.', $logs->records);
    }

    public function testOnlyForgottenSubscriptionsLeaveTheRecipientUnreachable(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/b'));

        self::assertSame(ChannelOutcome::Unreachable, $this->sender([new MockResponse('', ['http_code' => 404]), new MockResponse('', ['http_code' => 410])])->send('42', $this->rendered()));
        self::assertSame([], $this->subscriptions->of('42'));
    }

    public function testARefusalBesideAForgottenSubscriptionIsARefusal(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/b'));

        self::assertSame(ChannelOutcome::Refused, $this->sender([new MockResponse('', ['http_code' => 503]), new MockResponse('', ['http_code' => 410])])->send('42', $this->rendered()));
        self::assertSame(ChannelOutcome::Refused, $this->sender([new MockResponse('', ['http_code' => 503])])->send('42', $this->rendered()));
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/c'));
        self::assertSame(ChannelOutcome::Accepted, $this->sender([new MockResponse('', ['http_code' => 503]), new MockResponse('', ['http_code' => 201])])->send('42', $this->rendered()));
    }

    public function testTheSignatureIsReusedForTheBrowsersOfOnePushService(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/fcm/send/a'));
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/fcm/send/b'));

        $this->sender([new MockResponse('', ['http_code' => 201]), new MockResponse('', ['http_code' => 201])])->send('42', $this->rendered());

        self::assertCount(2, $this->requests);
        self::assertSame($this->headers($this->requests[0][2])['authorization'], $this->headers($this->requests[1][2])['authorization']);
    }

    public function testOnlyA2xxStatusIsASuccess(): void
    {
        foreach ([200, 204, 299] as $status) {
            $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
            $logs = new Logs();

            $this->sender([new MockResponse('', ['http_code' => $status])], $logs)->send('42', $this->rendered());

            // The library's notice that neither GMP nor BCMath is loaded depends on the PHP build, not on the status.
            self::assertSame([], array_values(array_filter($logs->records, static fn (string $record): bool => !str_starts_with($record, 'notice: '))), (string) $status);
        }

        foreach ([199, 300] as $status) {
            $logs = new Logs();

            $this->sender([new MockResponse('', ['http_code' => $status])], $logs)->send('42', $this->rendered());

            self::assertContains(\sprintf('warning: The push service fcm.googleapis.com refused a notification of type comment.replied: %d.', $status), $logs->records);
        }
    }

    public function testARefusalWithoutLoggerGoesUnnoticed(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));

        $this->sender([new MockResponse('', ['http_code' => 429])])->send('42', $this->rendered());

        self::assertCount(1, $this->requests);
        self::assertCount(1, $this->subscriptions->of('42'));
    }

    public function testTheDefaultsAreADayAndTenSeconds(): void
    {
        $this->subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        new MinishlinkWebPushSender(new NotificationTypes(new NotificationType('comment.replied')), $this->subscriptions, $this->vapid, $this->client([new MockResponse('', ['http_code' => 201])]))->send('42', $this->rendered());

        $headers = $this->headers($this->requests[0][2]);
        self::assertSame('86400', $headers['ttl']);
        self::assertSame('normal', $headers['urgency']);
        self::assertArrayNotHasKey('topic', $headers);
        self::assertEqualsWithDelta(10.0, $this->requests[0][2]['timeout'], 0.0001);
        self::assertEqualsWithDelta(10.0, MinishlinkWebPushSender::TIMEOUT, 0.0001);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function sender(array $responses, ?Logs $logs = null, int $ttl = WebPushMessage::TTL, float $timeout = MinishlinkWebPushSender::TIMEOUT): MinishlinkWebPushSender
    {
        return new MinishlinkWebPushSender(new NotificationTypes($this->type()), $this->subscriptions, $this->vapid, $this->client($responses), $ttl, $logs, $timeout);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses): MockHttpClient
    {
        return new MockHttpClient(
            function (string $method, string $url, array $options) use (&$responses): MockResponse {
                $this->requests[] = [$method, $url, $options];

                return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
            },
        );
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<string, string>
     */
    private function headers(array $options): array
    {
        $headers = [];

        foreach ((array) ($options['headers'] ?? []) as $line) {
            if (\is_string($line) && str_contains($line, ': ')) {
                [$name, $value] = explode(': ', $line, 2);
                $headers[strtolower($name)] = $value;
            }
        }

        return $headers;
    }

    private function type(): NotificationType
    {
        return new NotificationType('comment.replied', collapse: true, importance: 'high');
    }

    private function rendered(): RenderedNotification
    {
        return new RenderedNotification(new Notification('n1', 'comment.replied', '42', new SubjectReference('comment', '9'), new DateTimeImmutable('2026-11-08 10:00 UTC')), 'A reply', 'Ada answered you.', '/comments/9');
    }
}
