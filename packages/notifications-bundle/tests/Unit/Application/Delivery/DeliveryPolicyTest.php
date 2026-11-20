<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application\Delivery;

use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\Delivery\DeliveryPolicy;
use Phil\NotificationsBundle\Application\Delivery\RecipientRateLimit;
use Phil\NotificationsBundle\Tests\Unit\Fixture\ScriptedChannels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(DeliveryPolicy::class)]
#[CoversClass(RecipientRateLimit::class)]
final class DeliveryPolicyTest extends TestCase
{
    public function testTheFirstSenderThatServesTheChannelSendsOnIt(): void
    {
        $application = new ScriptedChannels(['sms' => ChannelOutcome::Accepted]);
        $bundle = new ScriptedChannels(['sms' => ChannelOutcome::Refused, 'email' => ChannelOutcome::Accepted]);
        $policy = new DeliveryPolicy([$application, $bundle]);

        self::assertSame($application, $policy->sender('sms'));
        self::assertSame($bundle, $policy->sender('email'));
        self::assertNull($policy->sender('push'));
        self::assertNull(new DeliveryPolicy()->sender('sms'));
    }

    public function testEachRecipientHasTheirOwnLimit(): void
    {
        $storage = new InMemoryStorage();
        $limit = new RecipientRateLimit(new RateLimiterFactory(['id' => 'n', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 hour'], $storage));

        self::assertTrue($limit->consume('42'));
        self::assertTrue($limit->consume('42'));
        self::assertFalse($limit->consume('42'));
        self::assertTrue($limit->consume('43'));
        self::assertNotNull($storage->fetch('n-'.RecipientRateLimit::PREFIX.'42'));
        self::assertSame('phil_notifications.', RecipientRateLimit::PREFIX);
    }
}
