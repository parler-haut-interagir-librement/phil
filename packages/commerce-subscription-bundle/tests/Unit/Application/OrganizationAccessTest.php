<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\Seats;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryOffers;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySeats;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An organization `guild` subscribes; its members use the features, through the membership the application tells.
 */
#[CoversClass(FeatureAccess::class)]
#[CoversClass(Seats::class)]
final class OrganizationAccessTest extends TestCase
{
    private InMemoryOffers $offers;

    private InMemorySubscriptions $repository;

    private InMemorySeats $seats;

    /** @var array<string, list<string>> member → organizations */
    private array $memberships = [];

    private FeatureAccess $access;

    private Subscriptions $subscriptions;

    protected function setUp(): void
    {
        $this->offers = new InMemoryOffers(
            Offers::free(),
            Offers::pro(),
            Offer::create('crew', 'studio', 3, 'Crew', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['crew:board', 'export'], 2),
        );
        $this->repository = new InMemorySubscriptions();
        $this->seats = new InMemorySeats();
        $memberships = &$this->memberships;
        $membership = new class($memberships) implements OrganizationMembership {
            /** @param array<string, list<string>> $memberships */
            public function __construct(private array &$memberships)
            {
            }

            #[Override]
            public function organizationsOf(string $member): array
            {
                return $this->memberships[$member] ?? [];
            }
        };
        $this->access = new FeatureAccess($this->repository, $this->offers, $membership, $this->seats);
        $this->subscriptions = new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], []), 2, 7, $this->access);
    }

    /**
     * Every member of the organization uses its offer without seats, after their own features; others do not.
     */
    public function testAnOrganizationOpensItsFeaturesToItsMembers(): void
    {
        $this->subscriptions->subscribe('guild', 'pro', $this->at('2026-04-01'));
        $this->subscriptions->subscribe('ann', 'free', $this->at('2026-04-01'));

        $this->memberships = ['ann' => ['guild', 'ann', 'guild'], 'bob' => ['guild']];

        self::assertSame(['projects:3', 'projects:unlimited', 'export'], $this->access->features('ann'));
        self::assertSame(['projects:unlimited', 'export'], $this->access->features('bob'));
        self::assertSame([], $this->access->features('eve'));
        self::assertSame(1, $this->repository->reads['ann'], 'the subscriber and the organizations in one read');
    }

    /**
     * Forgetting the organization forgets the members read through it.
     */
    public function testAChangeOfTheOrganizationIsSeenByItsMembers(): void
    {
        $guild = $this->subscriptions->subscribe('guild', 'free', $this->at('2026-04-01'));
        $this->memberships = ['ann' => ['guild']];
        self::assertSame(['projects:3'], $this->access->features('ann'));

        $this->subscriptions->cancel($guild, $this->at('2026-04-02'));
        $this->subscriptions->sweep($this->at('2026-05-02'));

        self::assertSame([], $this->access->features('ann'));
        self::assertSame(2, $this->repository->reads['ann']);
    }

    /**
     * An offer with two seats: the members holding one use it, a third waits; a released seat goes to them.
     */
    public function testSeatsOpenTheFeaturesToTheirHolders(): void
    {
        $guild = $this->subscriptions->subscribe('guild', 'crew', $this->at('2026-04-01'));
        $this->memberships = ['ann' => ['guild'], 'bob' => ['guild'], 'cat' => ['guild']];
        $seats = new Seats($this->offers, $this->seats, $this->access);
        self::assertSame([], $this->access->features('ann'));

        $ann = $seats->assign($guild, 'ann', $this->at('2026-04-02'));
        $seats->assign($guild, 'bob', $this->at('2026-04-03'));

        self::assertSame($ann, $seats->assign($guild, 'ann', $this->at('2026-04-04')), 'a seat held is returned');
        self::assertSame(['crew:board', 'export'], $this->access->features('ann'));
        self::assertSame(['crew:board', 'export'], $this->access->features('bob'));
        self::assertSame([], $this->access->features('cat'));
        self::assertSame(['ann', 'bob'], array_map(static fn (Seat $seat): string => $seat->member, $seats->holders($guild)));

        try {
            $seats->assign($guild, 'cat', $this->at('2026-04-05'));
            self::fail('A third seat was assigned.');
        } catch (SubscriptionRefused $subscriptionRefused) {
            self::assertSame(\sprintf('The 2 seats of the subscription %s are taken.', $guild->id), $subscriptionRefused->getMessage());
        }

        $seats->release($guild, 'ann');
        $seats->release($guild, 'ann');
        $seats->assign($guild, 'cat', $this->at('2026-04-06'));

        self::assertSame([], $this->access->features('ann'));
        self::assertSame(['crew:board', 'export'], $this->access->features('cat'));
    }

    /**
     * The organization itself uses its features without a seat.
     */
    public function testTheOrganizationItselfNeedsNoSeat(): void
    {
        $this->subscriptions->subscribe('guild', 'crew', $this->at('2026-04-01'));

        self::assertSame(['crew:board', 'export'], $this->access->features('guild'));
    }

    /**
     * An offer without seats takes none, and a member is named by a stable identifier.
     */
    public function testSeatsAreRefusedWhereTheyDoNotApply(): void
    {
        $seats = new Seats($this->offers, $this->seats);
        $pro = $this->subscriptions->subscribe('guild', 'pro', $this->at('2026-04-01'));
        $crew = $this->subscriptions->subscribe('guild', 'crew', $this->at('2026-04-01'));

        try {
            $seats->assign($pro, 'ann', $this->at('2026-04-02'));
            self::fail('A seat was assigned on an offer without seats.');
        } catch (SubscriptionRefused $subscriptionRefused) {
            self::assertSame('The offer "pro" counts no seats: every member of the organization uses it.', $subscriptionRefused->getMessage());
        }

        $this->expectException(InvalidValue::class);

        $seats->assign($crew, 'ann@example.com', $this->at('2026-04-02'));
    }

    /**
     * A subscription whose offer is gone gives nothing, and takes no seat.
     */
    public function testAnOfferGoneGivesNothing(): void
    {
        $guild = $this->subscriptions->subscribe('guild', 'crew', $this->at('2026-04-01'));
        $this->memberships = ['ann' => ['guild']];
        $this->offers->remove('crew');

        self::assertSame([], $this->access->features('ann'));

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('No offer is known as "crew".');

        new Seats($this->offers, $this->seats)->assign($guild, 'ann', $this->at('2026-04-02'));
    }

    /**
     * No more than 100 organizations of one member are read.
     */
    public function testAtMostAHundredOrganizationsAreRead(): void
    {
        $this->memberships = ['ann' => array_map(static fn (int $index): string => 'org-'.$index, range(1, 101))];
        $this->subscriptions->subscribe('org-100', 'free', $this->at('2026-04-01'));
        $this->subscriptions->subscribe('org-101', 'pro', $this->at('2026-04-01'));

        self::assertSame(['projects:3'], $this->access->features('ann'));
    }

    /**
     * Without a store of seats, an offer that counts seats opens nothing to the members.
     */
    public function testWithoutSeatsStoreSeatedOffersOpenNothing(): void
    {
        $this->subscriptions->subscribe('guild', 'crew', $this->at('2026-04-01'));
        $access = new FeatureAccess($this->repository, $this->offers, new class implements OrganizationMembership {
            #[Override]
            public function organizationsOf(string $member): array
            {
                return ['guild'];
            }
        });

        self::assertSame([], $access->features('ann'));
        self::assertInstanceOf(Subscription::class, $this->repository->subscriptions[0]);
    }

    /**
     * Without access to forget, seats are still assigned and released.
     */
    public function testSeatsWorkWithoutFeatureAccess(): void
    {
        $seats = new Seats($this->offers, $this->seats);
        $guild = $this->subscriptions->subscribe('guild', 'crew', $this->at('2026-04-01'));

        $seats->assign($guild, 'ann', $this->at('2026-04-02'));
        self::assertCount(1, $seats->holders($guild));

        $seats->release($guild, 'ann');
        self::assertSame([], $seats->holders($guild));
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00:00 UTC');
    }
}
