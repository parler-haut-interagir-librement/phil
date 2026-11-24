<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Unit\Subscription;

use DateTimeImmutable;
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Contract\SubscriptionStore;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;
use Phil\InventoryAlerts\Subscription\Price;
use Phil\InventoryAlerts\Subscription\SubscriberAlerts;
use Phil\InventoryAlerts\Subscription\Subscription;
use Phil\InventoryAlerts\Subscription\SubscriptionKind;
use Phil\InventoryAlerts\Subscription\Wave;
use Phil\InventoryAlerts\Subscription\WavePolicy;
use Phil\InventoryAlerts\Tests\Support\MemorySubscriptions;
use Phil\InventoryAlerts\Tests\Support\RecordingSubscriberNotifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

#[CoversClass(SubscriberAlerts::class)]
#[CoversClass(Wave::class)]
#[CoversClass(WavePolicy::class)]
#[CoversClass(Subscription::class)]
#[CoversClass(Price::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class SubscriberAlertsTest extends TestCase
{
    private MemorySubscriptions $store;

    private RecordingSubscriberNotifier $notifier;

    /** @var list<string> */
    private array $ids = [];

    protected function setUp(): void
    {
        $this->store = new MemorySubscriptions();
        $this->notifier = new RecordingSubscriberNotifier();
    }

    public function testASmallRestockTellsAWaveInTheOrderOfSubscription(): void
    {
        $this->subscribe(10);
        $alerts = new SubscriberAlerts($this->store, $this->notifier, new WavePolicy(2, 1, 500));
        $at = new DateTimeImmutable('2026-11-21 12:00');

        // Three units, two subscribers a unit: the first six.
        $wave = $alerts->restocked('MUG', 3, $at);

        self::assertInstanceOf(Wave::class, $wave);
        self::assertSame(SubscriptionKind::BackInStock, $wave->kind);
        self::assertSame('MUG', $wave->sku);
        self::assertNull($wave->price);
        self::assertTrue($wave->full);
        self::assertSame(\array_slice($this->ids, 0, 6), $wave->subscriptionIds());
        self::assertSame([$wave], $this->notifier->waves);
        self::assertSame([6], $this->store->limits);

        foreach ($wave->subscriptionIds() as $id) {
            self::assertSame($at, $this->store->rows[$id]->notifiedAt);
        }

        self::assertTrue($this->store->rows[$this->ids[6]]->isActive());
    }

    public function testTheNextWaveTellsTheNextSubscribers(): void
    {
        $this->subscribe(10);
        $alerts = new SubscriberAlerts($this->store, $this->notifier, new WavePolicy(2, 1, 500));
        $alerts->restocked('MUG', 3, new DateTimeImmutable());

        $wave = $alerts->restocked('MUG', 3, new DateTimeImmutable());

        self::assertSame(\array_slice($this->ids, 6), $wave?->subscriptionIds());
        self::assertFalse($wave->full);
        self::assertNull($alerts->restocked('MUG', 3, new DateTimeImmutable()));
        self::assertCount(2, $this->notifier->waves);
    }

    public function testAWaveOfOneTellsOneSubscriber(): void
    {
        $this->subscribe(3);
        $alerts = new SubscriberAlerts($this->store, $this->notifier, new WavePolicy(1, 1, 1));

        self::assertSame([$this->ids[0]], $alerts->restocked('MUG', 1, new DateTimeImmutable())?->subscriptionIds());
    }

    public function testNoUnitTellsNobodyAndReadsNothing(): void
    {
        $this->subscribe(3);
        $alerts = new SubscriberAlerts($this->store, $this->notifier);

        self::assertNull($alerts->restocked('MUG', 0, new DateTimeImmutable()));
        self::assertNull($alerts->restocked('MUG', -4, new DateTimeImmutable()));
        self::assertSame([], $this->store->limits);
        self::assertSame([], $this->notifier->waves);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function waveSizes(): iterable
    {
        yield 'one unit, the minimum' => [1, 10];
        yield 'five units' => [5, 10];
        yield 'six units' => [6, 12];
        yield 'many units, the maximum' => [1_000, 500];
        yield 'none' => [0, 0];
    }

    #[DataProvider('waveSizes')]
    public function testTheWaveIsSizedByTheUnitsBetweenItsMinimumAndMaximum(int $available, int $size): void
    {
        self::assertSame($size, new WavePolicy()->sizeFor($available));
    }

    public function testThePolicyKeepsItsSettings(): void
    {
        $policy = new WavePolicy(3, 1, 10_000);

        self::assertSame([3, 1, 10_000], [$policy->subscribersPerUnit, $policy->minimum, $policy->maximum]);
        self::assertSame(1, new WavePolicy(1, 1, 1)->sizeFor(5));
        self::assertSame(10_000, new WavePolicy(10_000, 1, 10_000)->sizeFor(1));
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function refusedPolicies(): iterable
    {
        yield 'no subscriber per unit' => [0, 1, 10, 'The number of subscribers per unit must be from 1 to 10,000.'];
        yield 'too many per unit' => [10_001, 1, 10_001, 'The number of subscribers per unit must be from 1 to 10,000.'];
        yield 'no minimum' => [1, 0, 10, 'The minimum of a wave must be from 1 to the maximum.'];
        yield 'a minimum above the maximum' => [1, 11, 10, 'The minimum of a wave must be from 1 to the maximum.'];
        yield 'a maximum above the bound' => [1, 1, 10_001, 'The maximum of a wave must be at most 10,000.'];
    }

    #[DataProvider('refusedPolicies')]
    public function testAPolicyOutOfRangeIsRefused(int $perUnit, int $minimum, int $maximum, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        new WavePolicy($perUnit, $minimum, $maximum);
    }

    public function testAPriceDropTellsTheSubscribersWhoseThresholdItReaches(): void
    {
        $at = new DateTimeImmutable('2026-11-21 09:00');
        $low = $this->priceDrop(1_000, 'CAD', $at);
        $high = $this->priceDrop(2_000, 'CAD', $at->modify('+1 minute'));
        $this->priceDrop(3_000, 'USD', $at->modify('+2 minutes'));
        $alerts = new SubscriberAlerts($this->store, $this->notifier, new WavePolicy(1, 1, 50));
        $price = new Price(1_500, 'CAD');

        $wave = $alerts->priceChanged('MUG', $price, $at);

        self::assertSame([$high->id], $wave?->subscriptionIds());
        self::assertSame(SubscriptionKind::PriceDrop, $wave->kind);
        self::assertSame($price, $wave->price);
        self::assertFalse($wave->full);
        self::assertSame([50], $this->store->limits);

        $wave = $alerts->priceChanged('MUG', new Price(1_000, 'CAD'), $at);

        self::assertSame([$low->id], $wave?->subscriptionIds());
        self::assertNull($alerts->priceChanged('MUG', new Price(1, 'CAD'), $at));
    }

    public function testAFullPriceWaveSaysSo(): void
    {
        $at = new DateTimeImmutable('2026-11-21 09:00');
        $this->priceDrop(1_000, 'CAD', $at);
        $this->priceDrop(1_000, 'CAD', $at);

        self::assertTrue(new SubscriberAlerts($this->store, $this->notifier, new WavePolicy(1, 1, 2))->priceChanged('MUG', new Price(900, 'CAD'), $at)?->full);
    }

    public function testASubscriptionIsMarkedOnlyOnceItsWaveIsSent(): void
    {
        $this->subscribe(2);
        $alerts = new SubscriberAlerts($this->store, new RecordingSubscriberNotifier(fail: true));

        try {
            $alerts->restocked('MUG', 5, new DateTimeImmutable());
            self::fail('The notifier failed.');
        } catch (RuntimeException) {
        }

        foreach ($this->store->rows as $row) {
            self::assertTrue($row->isActive());
        }
    }

    public function testWhatTheStoreReturnsIsFilteredSortedAndCutAgain(): void
    {
        $at = new DateTimeImmutable('2026-11-21 09:00');
        $email = EmailAddress::parse('ana@example.org');
        $later = new Subscription(str_repeat('b', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at->modify('+1 hour'));
        $earlier = new Subscription(str_repeat('c', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at);
        $sameTimeSmallerId = new Subscription(str_repeat('a', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at->modify('+1 hour'));
        $rows = [
            $later,
            new Subscription(str_repeat('d', 32), SubscriptionKind::BackInStock, 'CAP', $email, $at),
            new Subscription(str_repeat('e', 32), SubscriptionKind::PriceDrop, 'MUG', $email, $at, new Price(1, 'CAD')),
            new Subscription(str_repeat('f', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at)->notified($at),
            $sameTimeSmallerId,
            $earlier,
            new Subscription(str_repeat('0', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at->modify('+2 hours')),
        ];
        $store = self::createStub(SubscriptionStore::class);
        $store->method('active')->willReturn($rows);

        $wave = new SubscriberAlerts($store, $this->notifier, new WavePolicy(3, 1, 500))->restocked('MUG', 1, $at);

        self::assertSame([$earlier->id, $sameTimeSmallerId->id, $later->id], $wave?->subscriptionIds());
        self::assertTrue($wave->full);
    }

    public function testAPriceWaveRefusesAThresholdOfAnotherCurrencyTheStoreLetThrough(): void
    {
        $at = new DateTimeImmutable('2026-11-21 09:00');
        $email = EmailAddress::parse('ana@example.org');
        $rows = [
            new Subscription(str_repeat('a', 32), SubscriptionKind::PriceDrop, 'MUG', $email, $at, new Price(5_000, 'USD')),
            new Subscription(str_repeat('b', 32), SubscriptionKind::PriceDrop, 'MUG', $email, $at, new Price(5_000, 'CAD')),
            new Subscription(str_repeat('c', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at),
        ];
        $store = self::createStub(SubscriptionStore::class);
        $store->method('active')->willReturn($rows);

        $wave = new SubscriberAlerts($store, $this->notifier)->priceChanged('MUG', new Price(4_000, 'CAD'), $at);

        self::assertSame([str_repeat('b', 32)], $wave?->subscriptionIds());
    }

    public function testAWaveNeverCarriesAQuantity(): void
    {
        $properties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), new ReflectionClass(Wave::class)->getProperties());

        self::assertSame(['kind', 'sku', 'subscriptions', 'full', 'price'], $properties);
    }

    public function testTheSkuIsChecked(): void
    {
        $this->expectException(InvalidInput::class);

        new SubscriberAlerts($this->store, $this->notifier)->restocked('', 1, new DateTimeImmutable());
    }

    public function testTheSkuOfAPriceChangeIsChecked(): void
    {
        $this->expectException(InvalidInput::class);

        new SubscriberAlerts($this->store, $this->notifier)->priceChanged("MUG\0", new Price(1, 'CAD'), new DateTimeImmutable());
    }

    private function subscribe(int $count): void
    {
        $start = new DateTimeImmutable('2026-11-01 09:00');
        $subscriptions = [];

        for ($i = 0; $i < $count; ++$i) {
            $subscriptions[] = new Subscription(Subscription::newId(), SubscriptionKind::BackInStock, 'MUG', EmailAddress::parse('c'.$i.'@example.org'), $start->modify('+'.$i.' minutes'));
        }

        // Saved newest first: the store orders them.
        foreach (array_reverse($subscriptions) as $subscription) {
            $this->store->save($subscription);
        }

        $this->ids = array_map(static fn (Subscription $subscription): string => $subscription->id, $subscriptions);
    }

    private function priceDrop(int $threshold, string $currency, DateTimeImmutable $at): Subscription
    {
        $subscription = new Subscription(Subscription::newId(), SubscriptionKind::PriceDrop, 'MUG', EmailAddress::parse('p'.\count($this->store->rows).'@example.org'), $at, new Price($threshold, $currency));
        $this->store->save($subscription);

        return $subscription;
    }
}
