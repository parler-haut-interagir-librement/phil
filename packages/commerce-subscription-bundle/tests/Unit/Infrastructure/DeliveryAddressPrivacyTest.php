<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Address\Address;
use Phil\CommerceSubscriptionBundle\Application\ConfiguredPhoneRequirement;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Infrastructure\Privacy\DeliveryAddressPrivacy;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryDeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryAddressPrivacy::class)]
final class DeliveryAddressPrivacyTest extends TestCase
{
    private InMemorySubscriptions $subscriptions;

    private DeliveryAddresses $addresses;

    private DeliveryAddressPrivacy $privacy;

    protected function setUp(): void
    {
        $this->subscriptions = new InMemorySubscriptions();
        $this->addresses = new DeliveryAddresses($this->subscriptions, new InMemoryDeliveryAddresses($this->subscriptions), new ConfiguredPhoneRequirement(['courier']));
        $this->privacy = new DeliveryAddressPrivacy($this->subscriptions, $this->addresses);
    }

    public function testItNamesItsModule(): void
    {
        self::assertSame('commerce_subscription.delivery_addresses', $this->privacy->module()->value);
    }

    public function testASubscriberWithoutAddressHasNothingToExport(): void
    {
        $this->subscriptions->subscriptions[] = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));

        self::assertSame([], $this->privacy->export(DataSubject::fromString('account-1')));
    }

    public function testItExportsTheAddressesOfTheSubscriptionsAndOrders(): void
    {
        $subscription = $this->subscription();
        $this->subscriptions->subscriptions[] = Subscription::start(Offers::pro(), 'account-1', new DateTimeImmutable('2026-01-01'));
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $this->addresses->shipped('2026-0001', new DateTimeImmutable('2026-01-03T10:00:00+00:00'));

        $address = ['country' => 'CA', 'locality' => 'Montréal'];
        self::assertSame([
            'subscriptions' => [['subscription' => $subscription->id, 'address' => $address, 'method' => 'courier', 'phone' => '+15145550123', 'last_shipped_at' => '2026-01-03T10:00:00+00:00']],
            'orders' => [['order' => '2026-0001', 'subscription' => $subscription->id, 'address' => $address, 'method' => 'courier', 'phone' => '+15145550123', 'frozen_at' => '2026-01-01T10:00:00+00:00', 'shipped_at' => '2026-01-03T10:00:00+00:00']],
        ], $this->privacy->export(DataSubject::fromString('account-1')));
    }

    public function testAnOrderAloneIsExported(): void
    {
        $subscription = $this->subscription();
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $this->addresses->remove($subscription);

        $export = $this->privacy->export(DataSubject::fromString('account-1'));

        self::assertSame([], $export['subscriptions'] ?? null);
        self::assertIsArray($export['orders'] ?? null);
        self::assertCount(1, $export['orders']);
        self::assertSame([['order' => '2026-0001', 'subscription' => $subscription->id, 'address' => ['country' => 'CA', 'locality' => 'Montréal'], 'method' => 'courier', 'phone' => '+15145550123', 'frozen_at' => '2026-01-01T10:00:00+00:00', 'shipped_at' => null]], $export['orders']);
    }

    public function testASubscriptionNeverShippedToIsExported(): void
    {
        $subscription = $this->subscription();

        self::assertSame(['subscriptions' => [['subscription' => $subscription->id, 'address' => ['country' => 'CA', 'locality' => 'Montréal'], 'method' => 'courier', 'phone' => '+15145550123', 'last_shipped_at' => null]], 'orders' => []], $this->privacy->export(DataSubject::fromString('account-1')));
    }

    public function testTheErasureIsIdempotent(): void
    {
        $subscription = $this->subscription();
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01'));

        foreach ([1, 2] as $run) {
            $report = $this->privacy->erase(DataSubject::fromString('account-1'));

            self::assertSame('commerce_subscription.delivery_addresses', $report->module->value, 'run '.$run);
            self::assertSame(['phil_commerce_subscription.delivery_address' => ErasureEffect::Redacted, 'phil_commerce_subscription_order_address' => ErasureEffect::Deleted], $report->effects);
            self::assertSame([], $this->privacy->export(DataSubject::fromString('account-1')));
        }
    }

    private function subscription(): Subscription
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));
        $this->subscriptions->subscriptions[] = $subscription;
        $this->addresses->change($subscription, Address::of('CA', ['locality' => 'Montréal']), 'courier', '+15145550123');

        return $subscription;
    }
}
