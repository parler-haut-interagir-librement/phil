<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineDeliveryAddressRepository;
use Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle;
use Phil\CommerceSubscriptionBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A subscription that ships a box, through the container: its address kept, frozen in its first order, changed after
 * a move without touching that order, then erased once the subscription ended and its retention passed.
 */
#[CoversClass(PhilCommerceSubscriptionBundle::class)]
#[CoversClass(DoctrineDeliveryAddressRepository::class)]
final class DeliveryAddressFlowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->service(OfferRepository::class, OfferRepository::class)->add(Offer::create('box', 'boxes', 0, 'Box', Money::of(2_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital'));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testAnOrderKeepsItsAddressUntilTheRetentionPasses(): void
    {
        $subscriptions = $this->service(Subscriptions::class, Subscriptions::class);
        $addresses = $this->service(DeliveryAddresses::class, DeliveryAddresses::class);
        $subscription = $subscriptions->subscribe('account-1', 'box', new DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $addresses->change($subscription, Address::of('CA', ['givenName' => 'Ada', 'locality' => 'Montréal']), 'post', '+15145550123');
        $order = $subscriptions->order($subscription, '2026-0001', 'QC', new DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $subscription = $this->subscription($subscription->id);
        $addresses->change($subscription, Address::of('CA', ['givenName' => 'Ada', 'locality' => 'Québec']), 'post');
        $addresses->shipped('2026-0001', new DateTimeImmutable('2026-01-03T10:00:00+00:00'));

        $subscription->abandon(new DateTimeImmutable('2026-01-10T10:00:00+00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $frozen = $addresses->ofOrder('2026-0001');
        self::assertInstanceOf(DeliveryAddress::class, $frozen);
        self::assertSame('Montréal', $frozen->address->get(AddressField::Locality));
        self::assertNull($frozen->phone);
        self::assertSame('Québec', $this->subscription($subscription->id)->deliveryAddress()?->address->get(AddressField::Locality));

        self::assertSame(['addresses' => 0, 'copies' => 0], $addresses->purge(new DateTimeImmutable('2026-04-01T10:00:00+00:00')));
        self::assertSame(['addresses' => 1, 'copies' => 1], $addresses->purge(new DateTimeImmutable('2026-04-11T10:00:00+00:00')));
        $this->entityManager->clear();
        self::assertNull($this->subscription($subscription->id)->deliveryAddress());
        self::assertNull($addresses->ofOrder('2026-0001'));
    }

    private function subscription(string $id): Subscription
    {
        $subscription = $this->service(SubscriptionRepository::class, SubscriptionRepository::class)->find($id);
        self::assertInstanceOf(Subscription::class, $subscription);

        return $subscription;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
