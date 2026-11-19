<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineGiftRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineOfferRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSeatRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSubscriptionRepository;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The Doctrine adapters on an in-memory SQLite database, each read from a fresh unit of work.
 */
#[CoversClass(DoctrineOfferRepository::class)]
#[CoversClass(DoctrineSubscriptionRepository::class)]
#[CoversClass(DoctrineGiftRepository::class)]
#[CoversClass(DoctrineSeatRepository::class)]
final class DoctrineRepositoriesTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineOfferRepository $offers;

    private DoctrineSubscriptionRepository $subscriptions;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->offers = new DoctrineOfferRepository($this->entityManager);
        $this->subscriptions = new DoctrineSubscriptionRepository($this->entityManager);
        $this->offers->add($this->offer('free', 0, 0, 0));
        $this->offers->add($this->offer('pro', 1, 1_000, 0));
        $this->offers->add($this->offer('trial', 2, 2_000, 14));

        $this->entityManager->flush();
    }

    public function testOffersAreFoundByCodeAndFamily(): void
    {
        $this->offers->add(Offer::create('other', 'elsewhere', 0, 'Other', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital'));
        // Ranked after the others although their codes come first: a family is listed by rank, then by code.
        $this->offers->add($this->offer('basic', 3, 500, 0));
        $this->offers->add($this->offer('annual', 3, 500, 0));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame('Pro', $this->offers->find('pro')?->name());
        self::assertNull($this->offers->find('gone'));
        self::assertSame(['free', 'pro', 'trial', 'annual', 'basic'], array_map(static fn (Offer $offer): string => $offer->code, $this->offers->family('plans')));
        self::assertSame([], $this->offers->findByCodes([]));
        self::assertSame(['free', 'pro'], array_keys($this->offers->findByCodes(['free', 'gone', 'pro'])));
        self::assertSame('Pro', $this->offers->findByCodes(['pro'])['pro']->name());
    }

    public function testSubscriptionsAreReadByEachQuery(): void
    {
        $pending = $this->subscribe('a', 'pro', '2026-01-01');
        $awaiting = $this->subscribe('b', 'pro', '2026-01-02');
        $awaiting->awaitPayment('O-1', $this->at('2026-01-02'));

        $later = $this->subscribe('a', 'pro', '2026-01-03');
        $free = $this->subscribe('c', 'free', '2026-01-01');
        $trial = $this->subscribe('d', 'trial', '2026-01-01');
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame($pending->id, $this->subscriptions->find($pending->id)?->id);
        self::assertNull($this->subscriptions->find('gone'));
        self::assertSame([$pending->id, $later->id], $this->ids($this->subscriptions->forSubscriber('a')));
        self::assertSame([$pending->id, $free->id, $later->id], $this->ids($this->subscriptions->forSubscribers(['c', 'a'])));
        self::assertSame([], $this->subscriptions->forSubscribers([]));
        self::assertSame([$pending->id, $later->id], $this->ids($this->subscriptions->pendingWithoutOrder()));
        self::assertSame([$pending->id], $this->ids($this->subscriptions->pendingWithoutOrder(1)));
        self::assertSame([$pending->id, $awaiting->id], $this->ids($this->subscriptions->pendingCreatedBefore($this->at('2026-01-03'))));
        self::assertSame([$pending->id], $this->ids($this->subscriptions->pendingCreatedBefore($this->at('2026-01-03'), 1)));
        self::assertSame([$trial->id, $free->id], $this->ids($this->subscriptions->periodOverBy($this->at('2026-02-01'))));
        self::assertSame([$trial->id], $this->ids($this->subscriptions->periodOverBy($this->at('2026-02-01'), 1)));
        self::assertSame([], $this->subscriptions->periodOverBy($this->at('2026-01-14')));
        self::assertSame([$trial->id], $this->ids($this->subscriptions->periodOverBy($this->at('2026-01-15'))));
    }

    /**
     * A subscriber's subscriptions come back by their start, then by identifier, whatever the order they were stored in.
     */
    public function testASubscribersSubscriptionsComeBackByStartThenIdentifier(): void
    {
        $pro = $this->offers->find('pro') ?? self::fail();
        $later = Subscription::start($pro, 'a', $this->at('2026-02-01'));
        $first = Subscription::start($pro, 'a', $this->at('2026-01-01'));
        $second = Subscription::start($this->offers->find('free') ?? self::fail(), 'a', $this->at('2026-01-01'));

        foreach ([$later, $second, $first] as $subscription) {
            $this->subscriptions->add($subscription);
            $this->entityManager->flush();
        }

        $this->entityManager->clear();

        self::assertSame([$first->id, $second->id, $later->id], $this->ids($this->subscriptions->forSubscriber('a')));
        self::assertSame([$first->id, $second->id, $later->id], $this->ids($this->subscriptions->forSubscribers(['a'])));
    }

    public function testGiftsAreFoundByIdAndCode(): void
    {
        $gifts = new DoctrineGiftRepository($this->entityManager);
        $gift = Gift::buy($this->offers->find('pro') ?? self::fail(), StableIdentifier::of('buyer-1', 'buyer'), 1, GiftRenewal::BuyerPays, $this->at('2026-01-01'));
        $gift->awaitPayment('G-1', Money::of(1_000, Currency::of('CAD')));

        $code = $gift->paid('G-1', $this->at('2027-01-01'));
        $gifts->add($gift);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame($gift->id, $gifts->find($gift->id)?->id);
        self::assertNull($gifts->find('gone'));
        self::assertSame($gift->id, $gifts->findByCodeHash(Gift::hashOf($code) ?? 'none')?->id);
        self::assertNull($gifts->findByCodeHash(hash('sha256', 'nothing')));
    }

    public function testSeatsAreCountedInTheOrderTheyWereAssigned(): void
    {
        $seats = new DoctrineSeatRepository($this->entityManager);
        $guild = $this->subscribe('guild', 'free', '2026-01-01');
        $other = $this->subscribe('other', 'free', '2026-01-01');

        foreach ([['cat', '2026-01-03'], ['bob', '2026-01-02'], ['ann', '2026-01-02']] as [$member, $at]) {
            $seats->add(Seat::assign($guild, StableIdentifier::of($member, 'member'), $this->at($at)));
        }

        $seats->add(Seat::assign($other, StableIdentifier::of('ann', 'member'), $this->at('2026-01-01')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['ann', 'bob', 'cat'], array_map(static fn (Seat $seat): string => $seat->member, $seats->holders($guild->id)));
        self::assertSame(['ann', 'bob'], array_map(static fn (Seat $seat): string => $seat->member, $seats->holders($guild->id, 2)));
        self::assertSame(3, $seats->count($guild->id));
        self::assertSame(0, $seats->count('none'));
        self::assertSame('bob', $seats->find($guild->id, 'bob')?->member);
        self::assertNull($seats->find($guild->id, 'eve'));
        self::assertTrue($seats->holdsSeat($guild->id, 'ann', 1));
        self::assertFalse($seats->holdsSeat($guild->id, 'bob', 1));
        self::assertTrue($seats->holdsSeat($guild->id, 'bob', 2));
        self::assertFalse($seats->holdsSeat($guild->id, 'cat', 2));
        self::assertTrue($seats->holdsSeat($guild->id, 'cat', 3));
        self::assertFalse($seats->holdsSeat($guild->id, 'eve', 3));

        $seats->remove($seats->find($guild->id, 'ann') ?? self::fail());
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertTrue($seats->holdsSeat($guild->id, 'bob', 1));
        self::assertTrue($seats->holdsSeat($other->id, 'ann', 1));
    }

    private function subscribe(string $subscriber, string $offer, string $at): Subscription
    {
        $subscription = Subscription::start($this->offers->find($offer) ?? self::fail(), $subscriber, $this->at($at));
        $this->subscriptions->add($subscription);

        return $subscription;
    }

    private function offer(string $code, int $rank, int $price, int $trialDays): Offer
    {
        return Offer::create($code, 'plans', $rank, ucfirst($code), Money::of($price, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), $trialDays, 'digital');
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

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.'T00:00:00+00:00');
    }
}
