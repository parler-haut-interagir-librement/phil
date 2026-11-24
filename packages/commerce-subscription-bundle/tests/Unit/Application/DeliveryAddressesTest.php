<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Address\Address;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceSubscriptionBundle\Application\ConfiguredPhoneRequirement;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Application\Handler\PurgeDeliveryAddressesHandler;
use Phil\CommerceSubscriptionBundle\Application\Message\PurgeDeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryDeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryOffers;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DeliveryAddresses::class)]
#[CoversClass(ConfiguredPhoneRequirement::class)]
#[CoversClass(PurgeDeliveryAddressesHandler::class)]
#[CoversClass(Subscriptions::class)]
final class DeliveryAddressesTest extends TestCase
{
    private InMemorySubscriptions $repository;

    private InMemoryDeliveryAddresses $copies;

    private DeliveryAddresses $addresses;

    private Subscriptions $subscriptions;

    protected function setUp(): void
    {
        $offers = new InMemoryOffers(Offers::free(), Offers::pro(), Offers::team());
        $this->repository = new InMemorySubscriptions();
        $this->copies = new InMemoryDeliveryAddresses($this->repository);
        $this->addresses = new DeliveryAddresses($this->repository, $this->copies, new ConfiguredPhoneRequirement(['courier']));
        $this->subscriptions = new Subscriptions($offers, $this->repository, new OrderModifier([new OfferSellables($offers, $this->repository)], [], []), 2, addresses: $this->addresses);
    }

    public function testACarrierThatAsksForAPhoneGetsOne(): void
    {
        $subscription = $this->subscribe();

        $kept = $this->addresses->change($subscription, $this->address(), 'courier', '+15145550123');

        self::assertSame('+15145550123', $kept->phone);
        self::assertTrue($this->addresses->of($subscription)?->equals($kept));
    }

    public function testACarrierThatAsksForAPhoneRefusesAnAddressWithoutOne(): void
    {
        $subscription = $this->subscribe();

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('The carrier of the delivery method "courier" asks for a telephone number.');

        try {
            $this->addresses->change($subscription, $this->address(), 'courier');
        } finally {
            self::assertNull($subscription->deliveryAddress());
        }
    }

    public function testAnyOtherCarrierKeepsNoPhone(): void
    {
        $subscription = $this->subscribe();

        self::assertNull($this->addresses->change($subscription, $this->address(), 'post', '+15145550123')->phone);
        self::assertNull($subscription->deliveryAddress()?->phone);
        self::assertSame('post', $subscription->deliveryAddress()?->method);
    }

    public function testAnAddressIsRemoved(): void
    {
        $subscription = $this->subscribe();
        $this->addresses->change($subscription, $this->address(), 'post');

        $this->addresses->remove($subscription);

        self::assertNull($this->addresses->of($subscription));
    }

    /**
     * Each order placed freezes the address it was placed with; a later move changes the next orders only.
     */
    public function testEachOrderFreezesItsAddress(): void
    {
        $subscription = $this->subscribe();
        $this->addresses->change($subscription, $this->address(), 'post');
        $this->subscriptions->order($subscription, '2026-0001', 'QC', new DateTimeImmutable('2026-01-01'));

        $moved = Address::of('CA', ['givenName' => 'Ada', 'locality' => 'Québec']);
        $this->addresses->change($subscription, $moved, 'post');

        self::assertTrue($this->addresses->ofOrder('2026-0001')?->address->equals($this->address()));
        self::assertTrue($subscription->deliveryAddress()?->address->equals($moved));
        self::assertNull($this->addresses->ofOrder('2026-0002'));
    }

    public function testAnOrderWithoutAddressFreezesNothing(): void
    {
        $subscription = $this->subscribe();

        $this->subscriptions->order($subscription, '2026-0001', 'QC', new DateTimeImmutable('2026-01-01'));

        self::assertSame([], $this->copies->copies);
        self::assertNull($this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01')));
    }

    public function testFreezingTwiceKeepsTheFirstCopy(): void
    {
        $subscription = $this->subscribe();
        $this->addresses->change($subscription, $this->address(), 'post');
        $first = $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01'));
        $this->addresses->change($subscription, Address::of('FR', ['locality' => 'Paris']), 'post');

        self::assertSame($first, $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-02')));
        self::assertCount(1, $this->copies->copies);
    }

    public function testAShipmentIsRecordedOnTheCopyAndTheSubscription(): void
    {
        $subscription = $this->subscribe();
        $this->addresses->change($subscription, $this->address(), 'post');
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01'));

        $this->addresses->shipped('2026-0001', new DateTimeImmutable('2026-01-03'));
        $this->addresses->shipped('2026-0404', new DateTimeImmutable('2026-01-04'));

        self::assertEquals(new DateTimeImmutable('2026-01-03'), $this->copies->copies['2026-0001']->shippedAt());
        self::assertEquals(new DateTimeImmutable('2026-01-03'), $subscription->lastShippedAt());
    }

    public function testASubscribersAddressesAreListedAndErased(): void
    {
        $subscription = $this->subscribe();
        $other = $this->subscribe('account-2');
        $this->addresses->change($subscription, $this->address(), 'post');
        $this->addresses->change($other, $this->address(), 'post');
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01'));
        $this->addresses->freeze($subscription, '2026-0002', new DateTimeImmutable('2026-02-01'));
        $this->addresses->freeze($other, '2026-0003', new DateTimeImmutable('2026-02-01'));

        self::assertSame(['2026-0001', '2026-0002'], array_map(static fn (OrderAddress $copy): string => $copy->orderNumber, $this->addresses->copiesOf('account-1')));
        self::assertCount(1, $this->addresses->copiesOf('account-1', 1));

        self::assertSame(3, $this->addresses->erase('account-1'));
        self::assertSame(0, $this->addresses->erase('account-1'));
        self::assertNull($subscription->deliveryAddress());
        self::assertSame([], $this->addresses->copiesOf('account-1'));
        self::assertInstanceOf(DeliveryAddress::class, $other->deliveryAddress());
        self::assertCount(1, $this->addresses->copiesOf('account-2'));
    }

    /**
     * The purge counts the retention back from its moment, and reads batches until one comes back short.
     */
    public function testThePurgeReadsBatchesUntilOneIsShort(): void
    {
        foreach (['a', 'b', 'c'] as $number) {
            $subscription = $this->subscribe('account-'.$number);
            $this->addresses->change($subscription, $this->address(), 'post');
            $this->addresses->freeze($subscription, $number, new DateTimeImmutable('2026-01-01'));
        }

        self::assertSame(['addresses' => 0, 'copies' => 3], $this->addresses->purge(new DateTimeImmutable('2026-04-02'), 2));
        self::assertEquals([[new DateTimeImmutable('2026-01-02'), 2], [new DateTimeImmutable('2026-01-02'), 2]], $this->copies->purges);
        self::assertSame(['addresses' => 0, 'copies' => 0], $this->addresses->purge(new DateTimeImmutable('2026-04-02'), 2));
    }

    public function testThePurgeKeepsWhatIsWithinItsRetention(): void
    {
        $subscription = $this->subscribe();
        $this->addresses->change($subscription, $this->address(), 'post');
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01'));

        self::assertSame(['addresses' => 0, 'copies' => 0], $this->addresses->purge(new DateTimeImmutable('2026-04-01')));
        self::assertEquals([[new DateTimeImmutable('2026-01-01'), 500]], $this->copies->purges);
    }

    public function testThePurgeEndsAfterItsLastBatch(): void
    {
        $addresses = new DeliveryAddresses($this->repository, new class($this->repository) extends InMemoryDeliveryAddresses {
            public int $batches = 0;

            public function purge(DateTimeImmutable $cutoff, int $limit): array
            {
                ++$this->batches;

                return ['addresses' => $limit, 'copies' => 0];
            }
        }, new ConfiguredPhoneRequirement());

        self::assertSame(['addresses' => 1_000, 'copies' => 0], $addresses->purge(new DateTimeImmutable(), 1));
    }

    public function testABatchFullOfCopiesReadsAnother(): void
    {
        $store = new class($this->repository) extends InMemoryDeliveryAddresses {
            public int $batches = 0;

            public function purge(DateTimeImmutable $cutoff, int $limit): array
            {
                ++$this->batches;

                return ['addresses' => 0, 'copies' => 1 === $this->batches ? $limit : 0];
            }
        };

        self::assertSame(['addresses' => 0, 'copies' => 5], new DeliveryAddresses($this->repository, $store, new ConfiguredPhoneRequirement())->purge(new DateTimeImmutable(), 5));
        self::assertSame(2, $store->batches);
    }

    public function testTheHandlerPurgesAtTheClocksTime(): void
    {
        $subscription = $this->subscribe();
        $this->addresses->change($subscription, $this->address(), 'post');
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01'));

        $handler = new PurgeDeliveryAddressesHandler($this->addresses, new MockClock('2026-06-01'), 7);

        self::assertSame(['addresses' => 0, 'copies' => 1], $handler(new PurgeDeliveryAddresses()));
        self::assertEquals([[new DateTimeImmutable('2026-03-03'), 7]], $this->copies->purges);
    }

    public function testTheHandlerPurgesNowWithoutAClock(): void
    {
        new PurgeDeliveryAddressesHandler($this->addresses)(new PurgeDeliveryAddresses());

        [[$cutoff, $limit]] = $this->copies->purges;
        self::assertSame(500, $limit);
        self::assertEqualsWithDelta(new DateTimeImmutable('-90 days')->getTimestamp(), $cutoff->getTimestamp(), 5);
    }

    public function testTheRetentionIsBounded(): void
    {
        self::assertSame(90, $this->addresses->retentionDays());
        self::assertSame(1, new DeliveryAddresses($this->repository, $this->copies, new ConfiguredPhoneRequirement(), 1)->retentionDays());
        self::assertSame(3_650, new DeliveryAddresses($this->repository, $this->copies, new ConfiguredPhoneRequirement(), 3_650)->retentionDays());

        foreach ([0, 3_651] as $days) {
            try {
                new DeliveryAddresses($this->repository, $this->copies, new ConfiguredPhoneRequirement(), $days);
                self::fail(\sprintf('%d days were taken.', $days));
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('A delivery address is kept 1 to 3650 days after its last shipment; %d is not.', $days), $invalidValue->getMessage());
            }
        }
    }

    public function testAPurgeReadsBatchesOfOneOrMore(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A purge reads batches of one or more.');

        $this->addresses->purge(new DateTimeImmutable(), 0);
    }

    public function testThePhoneRequirementReadsItsMethods(): void
    {
        $phones = new ConfiguredPhoneRequirement(['courier', 'express']);

        self::assertTrue($phones->requiresPhone('courier'));
        self::assertTrue($phones->requiresPhone('express'));
        self::assertFalse($phones->requiresPhone('post'));
        self::assertFalse(new ConfiguredPhoneRequirement()->requiresPhone('courier'));
    }

    private function subscribe(string $subscriber = 'account-1'): Subscription
    {
        return $this->subscriptions->subscribe($subscriber, 'team', new DateTimeImmutable('2026-01-01'));
    }

    private function address(): Address
    {
        return Address::of('CA', ['givenName' => 'Ada', 'addressLine1' => '1 rue Saint-Paul', 'locality' => 'Montréal']);
    }
}
