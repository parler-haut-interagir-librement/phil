<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Subscription;

use DateTimeImmutable;
use Override;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Subscription\FeatureLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureLimits::class)]
final class FeatureLimitsTest extends TestCase
{
    public function testALimitIsReadFromAFeature(): void
    {
        $limits = $this->limits(['export', 'usage:exports:20', 'usage:projects:unlimited']);

        self::assertSame(20, $limits->limitFor('account-1', $this->meter('exports'))?->value);
        self::assertTrue($limits->limitFor('account-1', $this->meter('projects'))?->isUnlimited());
        self::assertNull($limits->limitFor('account-1', $this->meter('tokens')));
    }

    public function testTheMostGenerousLimitWins(): void
    {
        self::assertSame(50, $this->limits(['usage:exports:20', 'usage:exports:50', 'usage:exports:5'])->limitFor('a', $this->meter('exports'))?->value);
        self::assertTrue($this->limits(['usage:exports:20', 'usage:exports:unlimited', 'usage:exports:5'])->limitFor('a', $this->meter('exports'))?->isUnlimited());
    }

    public function testOnlyTheWholeMeterNameMatches(): void
    {
        $limits = $this->limits(['usage:exports-pdf:9', 'usage:export:8', 'xusage:exports:7', 'usage:exports']);

        self::assertNull($limits->limitFor('a', $this->meter('exports')));
        self::assertSame(9, $limits->limitFor('a', $this->meter('exports-pdf'))?->value);
    }

    public function testAFeatureThatIsNotALimitIsSkipped(): void
    {
        self::assertSame(3, $this->limits(['usage:exports:many', 'usage:exports:3', 'usage:exports:'])->limitFor('a', $this->meter('exports'))?->value);
        self::assertNull($this->limits(['usage:exports:many'])->limitFor('a', $this->meter('exports')));
    }

    public function testThePrefixIsConfigurable(): void
    {
        $limits = new FeatureLimits(static fn (string $subject): array => ['quota.exports:4', 'usage:exports:9'], 'quota.');

        self::assertSame(4, $limits->limitFor('a', $this->meter('exports'))?->value);
    }

    public function testTheFeaturesAreThoseOfTheSubject(): void
    {
        $limits = new FeatureLimits(static fn (string $subject): array => 'account-1' === $subject ? ['usage:exports:4'] : []);

        self::assertSame(4, $limits->limitFor('account-1', $this->meter('exports'))?->value);
        self::assertNull($limits->limitFor('account-2', $this->meter('exports')));
    }

    public function testTheFeaturesOfSubscriptionsAreRead(): void
    {
        $free = Offer::create('free', 'plans', 0, 'Free', Money::of(0, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'standard', ['usage:exports:3']);
        $plus = Offer::create('plus', 'boosts', 0, 'Plus', Money::of(0, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'standard', ['usage:exports:30', 'usage:projects:10']);
        $at = new DateTimeImmutable('2026-03-14T10:30:00+00:00');
        $access = new FeatureAccess(
            new readonly class([Subscription::start($free, 'account-1', $at), Subscription::start($plus, 'account-1', $at)]) implements SubscriptionRepository {
                /**
                 * @param list<Subscription> $subscriptions
                 */
                public function __construct(private array $subscriptions)
                {
                }

                #[Override]
                public function find(string $id): ?Subscription
                {
                    return null;
                }

                #[Override]
                public function forSubscriber(string $subscriber): array
                {
                    return array_values(array_filter($this->subscriptions, static fn (Subscription $subscription): bool => $subscription->subscriber === $subscriber));
                }

                #[Override]
                public function forSubscribers(array $subscribers): array
                {
                    return array_values(array_filter($this->subscriptions, static fn (Subscription $subscription): bool => \in_array($subscription->subscriber, $subscribers, true)));
                }

                #[Override]
                public function periodOverBy(DateTimeImmutable $at, ?int $limit = null): array
                {
                    return [];
                }

                #[Override]
                public function pendingWithoutOrder(?int $limit = null): array
                {
                    return [];
                }

                #[Override]
                public function pendingCreatedBefore(DateTimeImmutable $before, ?int $limit = null): array
                {
                    return [];
                }

                #[Override]
                public function add(Subscription $subscription): void
                {
                }
            },
            new readonly class(['free' => $free, 'plus' => $plus]) implements OfferRepository {
                /**
                 * @param array<string, Offer> $offers
                 */
                public function __construct(private array $offers)
                {
                }

                #[Override]
                public function find(string $code): ?Offer
                {
                    return $this->offers[$code] ?? null;
                }

                #[Override]
                public function findByCodes(array $codes): array
                {
                    return array_intersect_key($this->offers, array_flip($codes));
                }

                #[Override]
                public function family(string $family): array
                {
                    return [];
                }

                #[Override]
                public function add(Offer $offer): void
                {
                }
            },
        );

        $limits = FeatureLimits::fromSubscriptions($access);

        self::assertSame(30, $limits->limitFor('account-1', $this->meter('exports'))?->value);
        self::assertSame(10, $limits->limitFor('account-1', $this->meter('projects'))?->value);
        self::assertNull($limits->limitFor('account-2', $this->meter('exports')));
        self::assertNull(FeatureLimits::fromSubscriptions($access, 'quota:')->limitFor('account-1', $this->meter('exports')));
    }

    /**
     * @param list<string> $features
     */
    private function limits(array $features): FeatureLimits
    {
        return new FeatureLimits(static fn (string $subject): array => $features);
    }

    private function meter(string $name): Meter
    {
        return new Meter($name, CalendarPeriod::month(), Limit::of(1));
    }
}
