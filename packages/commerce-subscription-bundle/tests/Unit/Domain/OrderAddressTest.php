<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\Address\Address;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The delivery address kept on a subscription, and the copy frozen in an order.
 */
#[CoversClass(OrderAddress::class)]
#[CoversClass(Subscription::class)]
final class OrderAddressTest extends TestCase
{
    public function testASubscriptionKeepsItsAddressUntilForgotten(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));

        self::assertNull($subscription->deliveryAddress());
        self::assertFalse($subscription->forgetDeliveryAddress());

        $subscription->shipTo($this->delivery());
        self::assertTrue($subscription->deliveryAddress()?->equals($this->delivery()));

        self::assertTrue($subscription->forgetDeliveryAddress());
        self::assertNull($subscription->deliveryAddress());
        self::assertFalse($subscription->forgetDeliveryAddress());
    }

    public function testASubscriptionKeepsItsLatestShipment(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));

        self::assertNull($subscription->lastShippedAt());
        $subscription->shipped(new DateTimeImmutable('2026-02-01'));
        $subscription->shipped(new DateTimeImmutable('2026-01-15'));
        self::assertEquals(new DateTimeImmutable('2026-02-01'), $subscription->lastShippedAt());
        $subscription->shipped(new DateTimeImmutable('2026-03-01'));
        self::assertEquals(new DateTimeImmutable('2026-03-01'), $subscription->lastShippedAt());

        // A shipment alone, its address gone, still has something to forget.
        self::assertTrue($subscription->forgetDeliveryAddress());
        self::assertNull($subscription->lastShippedAt());
    }

    public function testACopyKeepsTheAddressItWasFrozenWith(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));
        $copy = OrderAddress::freeze($subscription, $this->delivery(), '2026-0001', new DateTimeImmutable('2026-01-01'));
        $subscription->shipTo(DeliveryAddress::of(Address::of('FR', ['locality' => 'Paris']), 'post'));

        self::assertSame('2026-0001', $copy->orderNumber);
        self::assertSame($subscription->id, $copy->subscriptionId);
        self::assertSame('account-1', $copy->subscriber);
        self::assertTrue($copy->address()->equals($this->delivery()));
        self::assertEquals(new DateTimeImmutable('2026-01-01'), $copy->frozenAt);
        self::assertEquals(new DateTimeImmutable('2026-01-01'), $copy->keptSince());
        self::assertNull($copy->shippedAt());
    }

    public function testACopyCountsFromItsLatestShipment(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));
        $copy = OrderAddress::freeze($subscription, $this->delivery(), '2026-0001', new DateTimeImmutable('2026-01-10'));

        $copy->shipped(new DateTimeImmutable('2026-01-12'));
        self::assertEquals(new DateTimeImmutable('2026-01-12'), $copy->shippedAt());
        self::assertEquals(new DateTimeImmutable('2026-01-12'), $copy->keptSince());

        $copy->shipped(new DateTimeImmutable('2026-01-11'));
        self::assertEquals(new DateTimeImmutable('2026-01-12'), $copy->shippedAt());
        self::assertEquals(new DateTimeImmutable('2026-01-12'), $copy->keptSince());

        // A shipment recorded before the copy was frozen leaves its freezing what the retention counts from.
        $early = OrderAddress::freeze($subscription, $this->delivery(), '2026-0002', new DateTimeImmutable('2026-01-10'));
        $early->shipped(new DateTimeImmutable('2026-01-09'));
        self::assertEquals(new DateTimeImmutable('2026-01-09'), $early->shippedAt());
        self::assertEquals(new DateTimeImmutable('2026-01-10'), $early->keptSince());
    }

    public function testAnOrderNumberFitsItsColumn(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));

        self::assertSame(str_repeat('n', 64), OrderAddress::freeze($subscription, $this->delivery(), str_repeat('n', 64), new DateTimeImmutable())->orderNumber);

        foreach (['', '  ', str_repeat('n', 65)] as $number) {
            try {
                OrderAddress::freeze($subscription, $this->delivery(), $number, new DateTimeImmutable());
                self::fail(\sprintf('"%s" was taken for an order number.', $number));
            } catch (InvalidValue $invalidValue) {
                self::assertSame('An order is numbered by 1 to 64 characters.', $invalidValue->getMessage());
            }
        }
    }

    private function delivery(): DeliveryAddress
    {
        return DeliveryAddress::of(Address::of('CA', ['givenName' => 'Ada', 'locality' => 'Montréal']), 'courier', '+15145550123');
    }
}
