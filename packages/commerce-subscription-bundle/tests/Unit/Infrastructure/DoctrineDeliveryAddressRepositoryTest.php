<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\Address\Address;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineDeliveryAddressRepository;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The copies and the erasure of the delivery addresses on an in-memory SQLite database, each read from a fresh unit
 * of work.
 */
#[CoversClass(DoctrineDeliveryAddressRepository::class)]
final class DoctrineDeliveryAddressRepositoryTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineDeliveryAddressRepository $copies;

    private Offer $offer;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->copies = new DoctrineDeliveryAddressRepository($this->entityManager);
        $this->offer = Offer::create('box', 'boxes', 0, 'Box', Money::of(2_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'physical');
        $this->entityManager->persist($this->offer);
        $this->entityManager->flush();
    }

    public function testCopiesAreFoundAndListedOldestFirst(): void
    {
        $subscription = $this->subscription('a');
        $this->copies->add(OrderAddress::freeze($subscription, $this->delivery(), 'O-2', $this->at('2026-02-01')));
        $this->copies->add(OrderAddress::freeze($subscription, $this->delivery(), 'O-1', $this->at('2026-01-01')));
        $this->copies->add(OrderAddress::freeze($subscription, $this->delivery(), 'O-0', $this->at('2026-02-01')));
        $this->copies->add(OrderAddress::freeze($this->subscription('b'), $this->delivery(), 'O-3', $this->at('2026-01-01')));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertTrue($this->copies->find('O-1')?->address()->equals($this->delivery()));
        self::assertNull($this->copies->find('O-9'));
        self::assertSame(['O-1', 'O-0', 'O-2'], $this->numbers($this->copies->ofSubscriber('a', 10)));
        self::assertSame(['O-1', 'O-0'], $this->numbers($this->copies->ofSubscriber('a', 2)));
        self::assertSame([], $this->copies->ofSubscriber('c', 10));
    }

    public function testASubscriberIsErasedOnce(): void
    {
        $a = $this->subscription('a', true);
        $shippedOnly = $this->subscription('a');
        $shippedOnly->shipped($this->at('2026-01-05'));
        $this->subscription('a');
        $b = $this->subscription('b', true);
        $this->copies->add(OrderAddress::freeze($a, $this->delivery(), 'O-1', $this->at('2026-01-01')));
        $this->copies->add(OrderAddress::freeze($b, $this->delivery(), 'O-2', $this->at('2026-01-01')));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(3, $this->copies->erase('a'));
        self::assertSame(0, $this->copies->erase('a'));

        $this->entityManager->clear();
        self::assertNull($this->reload($a)->deliveryAddress());
        self::assertSame(2, $this->reload($a)->version());
        self::assertNull($this->reload($shippedOnly)->lastShippedAt());
        self::assertInstanceOf(DeliveryAddress::class, $this->reload($b)->deliveryAddress());
        self::assertSame(1, $this->reload($b)->version());
        self::assertNull($this->copies->find('O-1'));
        self::assertNotNull($this->copies->find('O-2'));
    }

    /**
     * An ended subscription's address goes once its end and its last shipment are both past the cutoff; a copy goes
     * once kept since before it.
     */
    public function testThePurgeErasesWhatIsPastTheCutoff(): void
    {
        $endedLongAgo = $this->ended('a', '2026-01-01', null);
        $shippedLongAgo = $this->ended('b', '2026-01-01', '2026-01-02');
        $shippedRecently = $this->ended('c', '2026-01-01', '2026-03-15');
        $endedRecently = $this->ended('d', '2026-03-15', null);
        $active = $this->subscription('e', true);
        $active->shipped($this->at('2026-01-01'));
        $this->ended('f', '2026-01-01', null, false);
        $old = OrderAddress::freeze($active, $this->delivery(), 'O-old', $this->at('2026-01-01'));
        $kept = OrderAddress::freeze($active, $this->delivery(), 'O-kept', $this->at('2026-01-01'));
        $kept->shipped($this->at('2026-03-15'));

        $this->copies->add($old);
        $this->copies->add($kept);
        $this->copies->add(OrderAddress::freeze($active, $this->delivery(), 'O-new', $this->at('2026-03-15')));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['addresses' => 2, 'copies' => 1], $this->copies->purge($this->at('2026-03-01'), 10));
        self::assertSame(['addresses' => 0, 'copies' => 0], $this->copies->purge($this->at('2026-03-01'), 10));

        $this->entityManager->clear();
        self::assertNull($this->reload($endedLongAgo)->deliveryAddress());
        self::assertNull($this->reload($shippedLongAgo)->deliveryAddress());
        self::assertNull($this->reload($shippedLongAgo)->lastShippedAt());
        self::assertNotNull($this->reload($shippedRecently)->deliveryAddress());
        self::assertNotNull($this->reload($endedRecently)->deliveryAddress());
        self::assertNotNull($this->reload($active)->deliveryAddress());
        self::assertSame(['O-kept', 'O-new'], $this->numbers($this->copies->ofSubscriber('e', 10)));
    }

    public function testThePurgeReadsAtMostItsLimitOldestFirst(): void
    {
        $first = $this->ended('a', '2026-01-01', null);
        $second = $this->ended('b', '2026-01-02', null);
        $subscription = $this->subscription('c');
        $this->copies->add(OrderAddress::freeze($subscription, $this->delivery(), 'O-2', $this->at('2026-01-02')));
        $this->copies->add(OrderAddress::freeze($subscription, $this->delivery(), 'O-1', $this->at('2026-01-01')));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['addresses' => 1, 'copies' => 1], $this->copies->purge($this->at('2026-03-01'), 1));
        $this->entityManager->clear();
        self::assertNull($this->reload($first)->deliveryAddress());
        self::assertNotNull($this->reload($second)->deliveryAddress());
        self::assertSame(['O-2'], $this->numbers($this->copies->ofSubscriber('c', 10)));
    }

    private function ended(string $subscriber, string $endedAt, ?string $shippedAt, bool $withAddress = true): Subscription
    {
        $subscription = $this->subscription($subscriber, $withAddress);
        $subscription->abandon($this->at($endedAt));

        if (null !== $shippedAt) {
            $subscription->shipped($this->at($shippedAt));
        }

        return $subscription;
    }

    private function subscription(string $subscriber, bool $withAddress = false): Subscription
    {
        $subscription = Subscription::start($this->offer, $subscriber, $this->at('2025-12-01'));

        if ($withAddress) {
            $subscription->shipTo($this->delivery());
        }

        $this->entityManager->persist($subscription);

        return $subscription;
    }

    private function reload(Subscription $subscription): Subscription
    {
        $found = $this->entityManager->find(Subscription::class, $subscription->id);
        self::assertInstanceOf(Subscription::class, $found);

        return $found;
    }

    private function delivery(): DeliveryAddress
    {
        return DeliveryAddress::of(Address::of('CA', ['givenName' => 'Ada', 'locality' => 'Montréal']), 'post');
    }

    /**
     * @param list<OrderAddress> $copies
     *
     * @return list<string>
     */
    private function numbers(array $copies): array
    {
        return array_map(static fn (OrderAddress $copy): string => $copy->orderNumber, $copies);
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.'T00:00:00+00:00');
    }
}
