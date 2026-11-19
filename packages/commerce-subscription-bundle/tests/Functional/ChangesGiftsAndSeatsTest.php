<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceSubscriptionBundle\Application\Gifts;
use Phil\CommerceSubscriptionBundle\Application\Seats;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftState;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Repository\GiftRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SeatRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineGiftRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSeatRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A change of tier at once, a gift and seats through the container, each read back from a fresh unit of work.
 */
#[CoversClass(DoctrineGiftRepository::class)]
#[CoversClass(DoctrineSeatRepository::class)]
#[CoversClass(DoctrineSubscriptionRepository::class)]
final class ChangesGiftsAndSeatsTest extends KernelTestCase
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

        $offers = $this->service(OfferRepository::class, OfferRepository::class);
        $offers->add(Offer::create('basic', 'plans', 1, 'Basic', Money::of(1_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['basic']));
        $offers->add(Offer::create('premium', 'plans', 2, 'Premium', Money::of(3_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['premium']));
        $offers->add(Offer::create('crew', 'plans', 3, 'Crew', Money::of(5_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['crew'], 2));

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * The core's processors run the proration before the taxes: the 13.33 left after it are taxed, and the change
     * is stored with its order, then applied once paid.
     */
    public function testAnUpgradeIsProratedThenTaxed(): void
    {
        $subscriptions = $this->service(Subscriptions::class, Subscriptions::class);
        $orders = $this->service(OrderRepository::class, OrderRepository::class);
        $subscription = $subscriptions->subscribe('account-1', 'basic', $this->at('2026-04-01'));
        $orders->add($subscriptions->order($subscription, 'F-1', 'QC', $this->at('2026-04-01')));
        $subscriptions->paid($subscription, 'F-1', $this->at('2026-04-01'));

        $order = $subscriptions->changeNow($subscription, 'premium', 'C-1', 'QC', $this->at('2026-04-11'));
        self::assertNotNull($order);
        $orders->add($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(-1_667, $order->adjustmentsTotal(AdjustmentType::Proration)->minorUnits);
        self::assertSame(1_333 + 67 + 133, $order->total()->minorUnits);

        $repository = $this->service(SubscriptionRepository::class, SubscriptionRepository::class);
        $stored = $repository->find($subscription->id) ?? self::fail('The subscription is gone.');
        self::assertSame('premium', $stored->pendingChange()?->offerCode);
        self::assertSame(667, $stored->pendingChange()->credit->minorUnits);
        self::assertSame(1_000, $stored->periodAmount()->minorUnits);

        $subscriptions->paid($stored, 'C-1', $this->at('2026-04-11'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $changed = $repository->find($subscription->id);
        self::assertNotNull($changed);
        self::assertSame('premium', $changed->offerCode());
        self::assertNull($changed->pendingChange());
        self::assertSame(3_000, $changed->periodAmount()->minorUnits);
        self::assertSame([$subscription->id], array_map(static fn (Subscription $held): string => $held->id, $repository->forSubscribers(['nobody', 'account-1'])));
        self::assertSame([], $repository->forSubscribers([]));
    }

    public function testAGiftIsFoundByItsCode(): void
    {
        $gifts = $this->service(Gifts::class, Gifts::class);
        $gift = $gifts->buy('buyer-1', 'premium', 2, GiftRenewal::EndsAtTerm, $this->at('2026-04-01'));
        $this->service(OrderRepository::class, OrderRepository::class)->add($gifts->order($gift, 'G-1', 'QC', $this->at('2026-04-01')));
        $code = $gifts->paid($gift, 'G-1', $this->at('2026-04-01'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = $this->service(GiftRepository::class, GiftRepository::class);
        self::assertSame($gift->id, $repository->findByCodeHash(Gift::hashOf($code) ?? self::fail())?->id);
        self::assertNull($repository->findByCodeHash(hash('sha256', 'nothing')));

        $subscription = $gifts->redeem($code, 'friend-1', $this->at('2026-04-05'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $redeemed = $repository->find($gift->id) ?? self::fail('The gift is gone.');
        self::assertSame(GiftState::Redeemed, $redeemed->state());
        self::assertSame($subscription->id, $redeemed->subscriptionId());
        self::assertSame(3_000, $redeemed->periodAmount()->minorUnits);
        $stored = $this->service(SubscriptionRepository::class, SubscriptionRepository::class)->find($subscription->id);
        self::assertSame('2026-06-05', $stored?->periodEnd()?->format('Y-m-d'));
        self::assertTrue($stored->cancelsAtPeriodEnd());
    }

    public function testSeatsAreCountedInOrder(): void
    {
        $subscription = $this->service(Subscriptions::class, Subscriptions::class)->subscribe('guild', 'crew', $this->at('2026-04-01'));
        $seats = $this->service(Seats::class, Seats::class);
        $seats->assign($subscription, 'bob', $this->at('2026-04-02'));
        $seats->assign($subscription, 'ann', $this->at('2026-04-02'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = $this->service(SeatRepository::class, SeatRepository::class);
        self::assertSame(['ann', 'bob'], array_map(static fn (Seat $seat): string => $seat->member, $repository->holders($subscription->id)));
        self::assertSame(['ann'], array_map(static fn (Seat $seat): string => $seat->member, $repository->holders($subscription->id, 1)));
        self::assertSame(2, $repository->count($subscription->id));
        self::assertSame(0, $repository->count('other'));
        self::assertTrue($repository->holdsSeat($subscription->id, 'ann', 1));
        self::assertFalse($repository->holdsSeat($subscription->id, 'bob', 1), 'the same moment, after ann');
        self::assertTrue($repository->holdsSeat($subscription->id, 'bob', 2));
        self::assertFalse($repository->holdsSeat($subscription->id, 'cat', 2));

        $seat = $repository->find($subscription->id, 'ann') ?? self::fail('The seat is gone.');
        $repository->remove($seat);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertTrue($repository->holdsSeat($subscription->id, 'bob', 1));
        self::assertNull($repository->find($subscription->id, 'ann'));
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

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.'T00:00:00+00:00');
    }
}
