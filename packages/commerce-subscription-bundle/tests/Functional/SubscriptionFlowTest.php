<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineOfferRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSubscriptionRepository;
use Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle;
use Phil\CommerceSubscriptionBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A subscription from start to renewal through the container: offers stored, the first period ordered through the
 * core and taxed there, paid, swept a month later, renewed, and its features read — every step read back from a fresh
 * unit of work.
 */
#[CoversClass(PhilCommerceSubscriptionBundle::class)]
#[CoversClass(DoctrineOfferRepository::class)]
#[CoversClass(DoctrineSubscriptionRepository::class)]
final class SubscriptionFlowTest extends KernelTestCase
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
        $offers->add(Offer::create('free', 'studio', 0, 'Free', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['projects:3']));
        $offers->add(Offer::create('pro', 'studio', 1, 'Pro', Money::of(1_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['projects:unlimited', 'export']));

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testASubscriptionIsOrderedPaidAndRenewed(): void
    {
        $subscriptions = $this->service(Subscriptions::class, Subscriptions::class);
        $subscription = $subscriptions->subscribe('account-1', 'pro', new DateTimeImmutable('2026-01-31T10:00:00+00:00'));
        $this->entityManager->flush();

        [$due] = $subscriptions->sweep(new DateTimeImmutable('2026-01-31T10:00:00+00:00'));
        self::assertSame($subscription, $due);

        $order = $subscriptions->order($subscription, '2026-0001', 'QC', new DateTimeImmutable('2026-01-31T10:00:00+00:00'));
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        self::assertSame(1_150, $order->total()->minorUnits);

        $subscriptions->paid($subscription, '2026-0001', new DateTimeImmutable('2026-01-31T10:05:00+00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = $this->service(SubscriptionRepository::class, SubscriptionRepository::class);
        $stored = $repository->find($subscription->id);
        self::assertNotNull($stored);
        self::assertSame(SubscriptionState::Active, $stored->state());
        self::assertSame('2026-02-28T10:05:00+00:00', $stored->periodEnd()?->format(\DATE_ATOM));
        self::assertSame(['projects:unlimited', 'export'], $this->service(FeatureAccess::class, FeatureAccess::class)->features('account-1'));
        self::assertSame([], $repository->periodOverBy(new DateTimeImmutable('2026-02-28T10:00:00+00:00')));

        $due = $subscriptions->sweep(new DateTimeImmutable('2026-02-28T10:05:00+00:00'));
        self::assertSame([$subscription->id], array_map(static fn (Subscription $renewal): string => $renewal->id, $due));

        $subscriptions->order($due[0], '2026-0002', 'QC', new DateTimeImmutable('2026-02-28T10:05:00+00:00'));
        $subscriptions->paid($due[0], '2026-0002', new DateTimeImmutable('2026-03-01T09:00:00+00:00'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $renewed = $repository->find($subscription->id);
        self::assertNotNull($renewed);
        self::assertSame('2026-03-31T10:05:00+00:00', $renewed->periodEnd()?->format(\DATE_ATOM));
        self::assertSame(2, $renewed->periodsPaid());
        self::assertSame(3, $renewed->version());
        self::assertSame([$subscription->id], array_map(static fn (Subscription $held): string => $held->id, $repository->forSubscriber('account-1')));
        self::assertSame([], $repository->forSubscriber('account-2'));
    }

    /**
     * Each read of the sweep on its own: the periods over, the pending subscriptions to order, the pending ones to
     * abandon, each capped by the limit.
     */
    public function testTheSweepReadsEachKindOnItsOwn(): void
    {
        $subscriptions = $this->service(Subscriptions::class, Subscriptions::class);
        $old = $subscriptions->subscribe('a', 'pro', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $awaiting = $subscriptions->subscribe('b', 'pro', new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
        $this->service(OrderRepository::class, OrderRepository::class)->add($subscriptions->order($awaiting, 'O-1', null, new DateTimeImmutable('2026-01-02T00:00:00+00:00')));
        $recent = $subscriptions->subscribe('c', 'pro', new DateTimeImmutable('2026-01-20T00:00:00+00:00'));
        $free = $subscriptions->subscribe('d', 'free', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = $this->service(SubscriptionRepository::class, SubscriptionRepository::class);

        self::assertSame([$old->id, $recent->id], $this->ids($repository->pendingWithoutOrder()));
        self::assertSame([$old->id], $this->ids($repository->pendingWithoutOrder(1)));
        self::assertSame([$old->id, $awaiting->id], $this->ids($repository->pendingCreatedBefore(new DateTimeImmutable('2026-01-10T00:00:00+00:00'))));
        self::assertSame([$free->id], $this->ids($repository->periodOverBy(new DateTimeImmutable('2026-02-01T00:00:00+00:00'))));

        $due = $this->service(Subscriptions::class, Subscriptions::class)->sweep(new DateTimeImmutable('2026-01-21T00:00:00+00:00'));
        self::assertSame([$recent->id], $this->ids($due));
        $this->entityManager->flush();
        $this->entityManager->clear();
        self::assertSame(SubscriptionState::Ended, $repository->find($old->id)?->state());
        self::assertSame(SubscriptionState::Ended, $repository->find($awaiting->id)?->state());
    }

    /**
     * @param list<Subscription> $found
     *
     * @return list<string>
     */
    private function ids(array $found): array
    {
        return array_map(static fn (Subscription $subscription): string => $subscription->id, $found);
    }

    /**
     * A trial whose period is over is read with the periods over, and a subscriber's subscriptions come back in the
     * order they were made, whatever their identifiers.
     */
    public function testATrialIsReadOnceOverAndSubscriptionsByTheirStart(): void
    {
        $this->service(OfferRepository::class, OfferRepository::class)->add(Offer::create('trial', 'studio', 2, 'Trial', Money::of(2_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 14, 'digital', []));
        $subscriptions = $this->service(Subscriptions::class, Subscriptions::class);
        $later = $subscriptions->subscribe('a', 'trial', new DateTimeImmutable('2026-02-01T00:00:00+00:00'));
        $earlier = $subscriptions->subscribe('a', 'free', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = $this->service(SubscriptionRepository::class, SubscriptionRepository::class);

        self::assertSame(SubscriptionState::Trialing, $repository->find($later->id)?->state());
        self::assertSame([$earlier->id, $later->id], $this->ids($repository->forSubscriber('a')));
        self::assertSame([$earlier->id, $later->id], $this->ids($repository->periodOverBy(new DateTimeImmutable('2026-02-15T00:00:00+00:00'))));
    }

    public function testOffersComeBackByFamilyAndRank(): void
    {
        $offers = $this->service(OfferRepository::class, OfferRepository::class);

        self::assertSame(['free', 'pro'], array_map(static fn (Offer $offer): string => $offer->code, $offers->family('studio')));
        self::assertSame([], $offers->family('patrons'));
        self::assertSame('Pro', $offers->find('pro')?->name());
        self::assertSame([], $offers->findByCodes([]));
        $found = array_keys($offers->findByCodes(['pro', 'gone', 'free', 'pro']));
        sort($found);
        self::assertSame(['free', 'pro'], $found);
        self::assertSame(['pro'], array_keys($offers->findByCodes(['pro'])));
        self::assertSame('Pro', $offers->findByCodes(['pro'])['pro']->name());

        foreach ([['gold', 2], ['silver', 1], ['bronze', 1]] as [$code, $rank]) {
            $offers->add(Offer::create($code, 'patrons', $rank, ucfirst($code), Money::of(500, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', []));
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['bronze', 'silver', 'gold'], array_map(static fn (Offer $offer): string => $offer->code, $this->service(OfferRepository::class, OfferRepository::class)->family('patrons')), 'by rank, then by code');
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
