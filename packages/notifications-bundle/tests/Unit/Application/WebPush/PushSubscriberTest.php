<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application\WebPush;

use Phil\NotificationsBundle\Application\Exception\InvalidPushSubscription;
use Phil\NotificationsBundle\Application\WebPush\PushSubscriber;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryPushSubscriptions;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushSubscriber::class)]
final class PushSubscriberTest extends TestCase
{
    public function testASubscriptionOnAKnownPushServiceIsKept(): void
    {
        $subscriptions = new InMemoryPushSubscriptions();
        $subscriber = new PushSubscriber($subscriptions);

        foreach (['https://fcm.googleapis.com/fcm/send/a', 'https://updates.push.services.mozilla.com/wpush/v2/b', 'https://wns2-bl2p.notify.windows.com/w/?token=c', 'https://web.push.apple.com/d'] as $endpoint) {
            self::assertSame($endpoint, $subscriber->subscribe('42', Browser::json($endpoint))->endpoint);
        }

        self::assertCount(4, $subscriptions->of('42'));
        self::assertSame(['fcm.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'], PushSubscriber::PUSH_SERVICES);
    }

    public function testAnotherHostIsRefused(): void
    {
        $subscriptions = new InMemoryPushSubscriptions();

        try {
            new PushSubscriber($subscriptions)->subscribe('42', Browser::json('https://push.example.com/a'));
            self::fail('Refused');
        } catch (InvalidPushSubscription $invalidPushSubscription) {
            self::assertSame('The push service "push.example.com" is not one this site sends to.', $invalidPushSubscription->getMessage());
        }

        self::assertSame([], $subscriptions->of('42'));
        self::assertSame('https://push.example.com/a', new PushSubscriber($subscriptions, [])->subscribe('42', Browser::json('https://push.example.com/a'))->endpoint);
        self::assertSame('https://push.example.com/b', new PushSubscriber($subscriptions, ['example.com'])->subscribe('42', Browser::json('https://push.example.com/b'))->endpoint);
    }

    public function testTheRecipientIsAnIdentifier(): void
    {
        $this->expectException(InvalidNotification::class);

        new PushSubscriber(new InMemoryPushSubscriptions())->subscribe('a b', Browser::json());
    }

    public function testAnEndpointIsForgottenByWhoeverSubscribedIt(): void
    {
        $subscriptions = new InMemoryPushSubscriptions();
        $subscriber = new PushSubscriber($subscriptions);
        $subscriber->subscribe('42', Browser::json('https://fcm.googleapis.com/fcm/send/a%20b'));

        self::assertFalse($subscriber->unsubscribe('43', '{"endpoint":"https://fcm.googleapis.com/fcm/send/a%20b"}'));
        self::assertFalse($subscriber->unsubscribe('42', '{"endpoint":"https://127.0.0.1/x"}'));
        self::assertTrue($subscriber->unsubscribe('42', '{"endpoint":"https://FCM.googleapis.com:443/fcm/send/a%20b"}'));
        self::assertSame([], $subscriptions->of('42'));
        self::assertFalse($subscriber->unsubscribe('42', '{"endpoint":"https://fcm.googleapis.com/fcm/send/a%20b"}'));
    }

    public function testAnUnsubscriptionIsReadUpToItsLimits(): void
    {
        $subscriber = new PushSubscriber(new InMemoryPushSubscriptions());
        $json = '{"endpoint":"https://fcm.googleapis.com/x"}';
        $exact = $json.str_repeat(' ', PushSubscription::MAX_JSON - \strlen($json));

        self::assertSame(4096, \strlen($exact));
        self::assertFalse($subscriber->unsubscribe('42', $exact));
        self::assertFalse($subscriber->unsubscribe('42', '{"endpoint":"https://fcm.googleapis.com/x","x":[[1]]}'));

        $this->expectExceptionObject(InvalidPushSubscription::because('An unsubscription is a JSON object with an "endpoint".'));
        $subscriber->unsubscribe('42', $exact.' ');
    }

    public function testAnUnsubscriptionThatIsTooDeepIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPushSubscription::because('An unsubscription is a JSON object with an "endpoint".'));

        new PushSubscriber(new InMemoryPushSubscriptions())->unsubscribe('42', '{"endpoint":"https://fcm.googleapis.com/x","x":[[[1]]]}');
    }

    public function testAnUnsubscriptionNamesAnEndpoint(): void
    {
        $subscriber = new PushSubscriber(new InMemoryPushSubscriptions());

        foreach (['nope', '{"endpoint":5}', '[]', '{"endpoint":"'.str_repeat('a', 4096).'"}'] as $json) {
            try {
                $subscriber->unsubscribe('42', $json);
                self::fail($json);
            } catch (InvalidPushSubscription $invalidPushSubscription) {
                self::assertSame('An unsubscription is a JSON object with an "endpoint".', $invalidPushSubscription->getMessage());
            }
        }
    }
}
