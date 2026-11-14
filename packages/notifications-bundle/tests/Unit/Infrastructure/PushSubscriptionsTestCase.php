<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use PHPUnit\Framework\TestCase;

/**
 * What every store of push subscriptions does, whatever keeps it.
 */
abstract class PushSubscriptionsTestCase extends TestCase
{
    /**
     * @param positive-int $limit
     */
    abstract protected function subscriptions(int $limit = 10): PushSubscriptions;

    /**
     * Lets time pass between two subscriptions, for the stores that date them.
     */
    abstract protected function later(): void;

    public function testASubscriptionIsKeptForItsRecipient(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/fcm/send/a', PushSubscription::AESGCM));

        $kept = $subscriptions->of('42');

        self::assertCount(1, $kept);
        self::assertSame('https://fcm.googleapis.com/fcm/send/a', $kept[0]->endpoint);
        self::assertSame('fcm.googleapis.com', $kept[0]->host);
        self::assertSame(Browser::key(), $kept[0]->publicKey);
        self::assertSame(Browser::AUTH, $kept[0]->authToken);
        self::assertSame('aesgcm', $kept[0]->contentEncoding);
        self::assertSame([], $subscriptions->of('43'));
    }

    public function testTheLatestComeFirstAndAnEndpointSubscribedAgainMovesToItsLastRecipient(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $this->later();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/b'));
        $this->later();
        $subscriptions->subscribe('43', Browser::subscription('https://fcm.googleapis.com/c'));

        self::assertSame(['https://fcm.googleapis.com/b', 'https://fcm.googleapis.com/a'], $this->endpoints($subscriptions, '42'));

        $this->later();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        self::assertSame(['https://fcm.googleapis.com/a', 'https://fcm.googleapis.com/b'], $this->endpoints($subscriptions, '42'));

        $this->later();
        $subscriptions->subscribe('43', Browser::subscription('https://fcm.googleapis.com/a'));
        self::assertSame(['https://fcm.googleapis.com/b'], $this->endpoints($subscriptions, '42'));
        self::assertSame(['https://fcm.googleapis.com/a', 'https://fcm.googleapis.com/c'], $this->endpoints($subscriptions, '43'));
    }

    public function testARecipientKeepsTheirLatestSubscriptions(): void
    {
        $subscriptions = $this->subscriptions(2);

        foreach (['a', 'b', 'c', 'd'] as $path) {
            $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/'.$path));
            $this->later();
        }

        $subscriptions->subscribe('43', Browser::subscription('https://fcm.googleapis.com/e'));

        self::assertSame(['https://fcm.googleapis.com/d', 'https://fcm.googleapis.com/c'], $this->endpoints($subscriptions, '42'));
        self::assertSame(['https://fcm.googleapis.com/e'], $this->endpoints($subscriptions, '43'));

        $single = $this->subscriptions(1);
        $single->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $single->subscribe('42', Browser::subscription('https://fcm.googleapis.com/b'));
        self::assertSame(['https://fcm.googleapis.com/b'], $this->endpoints($single, '42'));
    }

    public function testAnEndpointTakenFromAnotherRecipientCountsInTheLimitOfTheNewOne(): void
    {
        $subscriptions = $this->subscriptions(2);
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $this->later();
        $subscriptions->subscribe('43', Browser::subscription('https://fcm.googleapis.com/c'));
        $this->later();
        $subscriptions->subscribe('43', Browser::subscription('https://fcm.googleapis.com/d'));
        $this->later();
        $subscriptions->subscribe('43', Browser::subscription('https://fcm.googleapis.com/a'));

        self::assertSame([], $subscriptions->of('42'));
        self::assertSame(['https://fcm.googleapis.com/a', 'https://fcm.googleapis.com/d'], $this->endpoints($subscriptions, '43'));
    }

    public function testAnEndpointIsUnsubscribedByItsRecipientOnly(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/b'));

        self::assertFalse($subscriptions->unsubscribe('43', 'https://fcm.googleapis.com/a'));
        self::assertFalse($subscriptions->unsubscribe('42', 'https://fcm.googleapis.com/z'));
        self::assertTrue($subscriptions->unsubscribe('42', 'https://fcm.googleapis.com/a'));
        self::assertFalse($subscriptions->unsubscribe('42', 'https://fcm.googleapis.com/a'));
        self::assertSame(['https://fcm.googleapis.com/b'], $this->endpoints($subscriptions, '42'));
    }

    public function testAnEndpointOrARecipientIsForgotten(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/b'));
        $subscriptions->subscribe('43', Browser::subscription('https://fcm.googleapis.com/c'));

        $subscriptions->forget('https://fcm.googleapis.com/a');
        $subscriptions->forget('https://fcm.googleapis.com/z');
        self::assertSame(['https://fcm.googleapis.com/b'], $this->endpoints($subscriptions, '42'));

        $subscriptions->forgetRecipient('42');
        self::assertSame([], $subscriptions->of('42'));
        self::assertSame(['https://fcm.googleapis.com/c'], $this->endpoints($subscriptions, '43'));
    }

    /**
     * @return list<string>
     */
    protected function endpoints(PushSubscriptions $subscriptions, string $recipient): array
    {
        return array_map(static fn (PushSubscription $subscription): string => $subscription->endpoint, $subscriptions->of($recipient));
    }
}
