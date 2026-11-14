<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryPushSubscriptions;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryPushSubscriptions::class)]
final class InMemoryPushSubscriptionsTest extends PushSubscriptionsTestCase
{
    public function testAResetForgetsEverything(): void
    {
        $subscriptions = new InMemoryPushSubscriptions();
        $subscriptions->subscribe('42', Browser::subscription());
        $subscriptions->reset();

        self::assertSame([], $subscriptions->of('42'));
        self::assertSame(10, InMemoryPushSubscriptions::LIMIT);
    }

    public function testTenSubscriptionsAreKeptByDefault(): void
    {
        $subscriptions = new InMemoryPushSubscriptions();

        foreach (range(1, 11) as $i) {
            $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/'.$i));
        }

        self::assertCount(10, $subscriptions->of('42'));
        self::assertSame('https://fcm.googleapis.com/11', $subscriptions->of('42')[0]->endpoint);
    }

    #[Override]
    protected function subscriptions(int $limit = 10): PushSubscriptions
    {
        return new InMemoryPushSubscriptions($limit);
    }

    #[Override]
    protected function later(): void
    {
    }
}
