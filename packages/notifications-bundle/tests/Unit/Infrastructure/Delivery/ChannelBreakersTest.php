<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure\Delivery;

use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\CircuitState;
use Phil\NotificationsBundle\Infrastructure\CircuitBreaker\ChannelBreakers;
use Phil\NotificationsBundle\Tests\Unit\Fixture\InMemoryCircuits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ChannelBreakers::class)]
final class ChannelBreakersTest extends TestCase
{
    public function testTheCircuitOfTheTransportComesBeforeTheOneOfTheChannel(): void
    {
        $breakers = new ChannelBreakers($this->breakers('notifications.chat', 'notifications.chat.slack', 'notifications.sms', 'notifications.web_push'));

        self::assertSame('notifications.chat.slack', $breakers->of('chat/slack')?->key->value);
        self::assertSame('notifications.chat', $breakers->of('chat/discord')?->key->value);
        self::assertSame('notifications.chat', $breakers->of('chat')?->key->value);
        self::assertSame('notifications.sms', $breakers->of('sms/twilio')?->key->value);
        self::assertSame('notifications.web_push', $breakers->of('web_push')?->key->value);
        self::assertNull($breakers->of('email'));
    }

    public function testATransportTheCircuitKeysDoNotAllowFallsBackOnItsChannel(): void
    {
        $breakers = new ChannelBreakers($this->breakers('notifications.chat'));

        self::assertSame('notifications.chat', $breakers->of('chat/slack-')?->key->value);
        self::assertNull(new ChannelBreakers($this->breakers('notifications.sms'))->of('chat/slack-'));
    }

    public function testWithoutBreakersNoChannelHasOne(): void
    {
        $breakers = new ChannelBreakers();
        $breakers->recordFailure('sms');
        $breakers->recordSuccess('sms');

        self::assertNull($breakers->of('sms'));
        self::assertFalse($breakers->isOpen('sms'));
    }

    public function testFailuresOpenTheCircuitOfTheChannelAndASuccessOfTheProbeClosesIt(): void
    {
        $clock = new MockClock();
        $store = new InMemoryCircuits();
        $breakers = new ChannelBreakers(new CircuitBreakers([new CircuitBreaker(CircuitKey::of('notifications.sms'), CircuitPolicy::of(50, 2, 60, 30), $store, new CircuitOverrides(), $clock)]));

        $breakers->recordFailure('sms');
        self::assertFalse($breakers->isOpen('sms'));
        $breakers->recordFailure('sms/twilio');
        self::assertTrue($breakers->isOpen('sms'));
        self::assertFalse($breakers->isOpen('email'));
        $breakers->recordFailure('email');
        $breakers->recordSuccess('email');

        $clock->sleep(31);
        self::assertFalse($breakers->isOpen('sms'));
        $breakers->recordSuccess('sms');
        self::assertSame(CircuitState::Closed, $breakers->of('sms')?->state());
    }

    private function breakers(string ...$keys): CircuitBreakers
    {
        $store = new InMemoryCircuits();
        $clock = new MockClock();

        return new CircuitBreakers(array_map(static fn (string $key): CircuitBreaker => new CircuitBreaker(CircuitKey::of($key), CircuitPolicy::of(50, 2, 60, 30), $store, new CircuitOverrides(), $clock), array_values($keys)));
    }
}
