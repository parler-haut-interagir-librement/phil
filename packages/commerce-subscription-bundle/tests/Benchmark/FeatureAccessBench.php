<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Generator;
use Override;
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineOfferRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSeatRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSubscriptionRepository;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A feature check guards pages and API calls; the first check of each request reads the subscriber's subscriptions
 * and their offers, the next ones hit the memoised list. A member of an organization reads the organization's
 * subscriptions in the same query, and their seat on an offer that counts seats.
 *
 * @BeforeMethods("setUp")
 */
final readonly class FeatureAccessBench
{
    private EntityManager $entityManager;

    private FeatureAccess $access;

    private FeatureAccess $throughOrganization;

    public function __construct()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 2).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->access = new FeatureAccess(new DoctrineSubscriptionRepository($this->entityManager), new DoctrineOfferRepository($this->entityManager));
        $membership = new class implements OrganizationMembership {
            #[Override]
            public function organizationsOf(string $member): array
            {
                return ['org-42'];
            }
        };
        $this->throughOrganization = new FeatureAccess(new DoctrineSubscriptionRepository($this->entityManager), new DoctrineOfferRepository($this->entityManager), $membership, new DoctrineSeatRepository($this->entityManager));
    }

    /**
     * @param array{subscriptions: int} $params
     */
    public function setUp(array $params): void
    {
        $at = new DateTimeImmutable('2026-11-05 12:00:00');
        $currency = Currency::of('CAD');

        for ($i = 0; $i < $params['subscriptions']; ++$i) {
            $offer = Offer::create('plan-'.$i, 'family-'.$i, 0, 'Plan '.$i, Money::zero($currency), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['feature-'.$i, 'export', 'seats:'.$i]);
            $this->entityManager->persist($offer);
            $this->entityManager->persist(Subscription::start($offer, 'account-42', $at));
            // Other subscribers share the table, as in production.
            $this->entityManager->persist(Subscription::start($offer, 'account-'.(1_000 + $i), $at));
            $this->entityManager->persist(Subscription::start($offer, 'org-42', $at));
        }

        $crew = Offer::create('crew', 'crew', 0, 'Crew', Money::zero($currency), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['crew'], 5);
        $this->entityManager->persist($crew);
        $organization = Subscription::start($crew, 'org-42', $at);
        $this->entityManager->persist($organization);
        $this->entityManager->persist(Seat::assign($organization, StableIdentifier::of('member-42', 'member'), $at));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->access->grants('account-42', 'export');
    }

    /**
     * A later check of the same request: the features are memoised.
     *
     * @ParamProviders("subscribers")
     */
    public function benchGrantsWarm(): void
    {
        $this->access->grants('account-42', 'seats:0');
    }

    /**
     * The first check of a request: a fresh unit of work, the subscriptions read, then each one's offer.
     *
     * @ParamProviders("subscribers")
     *
     * @Revs(20)
     */
    public function benchGrantsCold(): void
    {
        $this->entityManager->clear();
        $this->access->reset();
        $this->access->grants('account-42', 'seats:0');
    }

    /**
     * The first check of a member: their subscriptions and the organization's in one query, the offers in another, and
     * their seat.
     *
     * @ParamProviders("subscribers")
     *
     * @Revs(20)
     */
    public function benchGrantsColdThroughAnOrganization(): void
    {
        $this->entityManager->clear();
        $this->throughOrganization->reset();
        $this->throughOrganization->grants('member-42', 'crew');
    }

    /**
     * @return Generator<string, array{subscriptions: int}>
     */
    public function subscribers(): Generator
    {
        yield '1 subscription' => ['subscriptions' => 1];
        yield '3 subscriptions' => ['subscriptions' => 3];
        yield '10 subscriptions' => ['subscriptions' => 10];
    }
}
