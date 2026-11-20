<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\Dunning\DunningAccess;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DunningStage;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The dunning stages of an offer, and how long a subscription has been past due.
 */
#[CoversClass(DunningStage::class)]
#[CoversClass(Offer::class)]
#[CoversClass(Subscription::class)]
final class DunningTest extends TestCase
{
    public function testAStageKeepsEveryFeatureSomeOrNone(): void
    {
        $full = DunningStage::full(0);
        $reduced = DunningStage::reduced(7, ['export', 'export', 'read']);
        $cut = DunningStage::cut(365);

        self::assertSame([0, DunningAccess::Full, []], [$full->afterDays, $full->access, $full->features]);
        self::assertSame([7, DunningAccess::Reduced, ['export', 'read']], [$reduced->afterDays, $reduced->access, $reduced->features]);
        self::assertSame([365, DunningAccess::None, []], [$cut->afterDays, $cut->access, $cut->features]);

        foreach ([$full, $reduced, $cut] as $stage) {
            self::assertEquals($stage, DunningStage::fromArray($stage->toArray()));
        }

        self::assertSame(['after_days' => 7, 'access' => 'reduced', 'features' => ['export', 'read']], $reduced->toArray());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedDays(): iterable
    {
        yield 'before the failure' => [-1];
        yield 'more than a year' => [366];
    }

    #[DataProvider('refusedDays')]
    public function testAStageStartsWithinAYear(int $days): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A dunning stage starts 0 to 365 days past due.');

        DunningStage::cut($days);
    }

    public function testAReducedAccessKeepsAFeature(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A reduced access keeps one feature or more; a stage that keeps none cuts access.');

        DunningStage::reduced(3, []);
    }

    public function testAnOfferKeepsEveryFeatureWithoutStages(): void
    {
        $pro = Offers::pro();

        self::assertSame([], $pro->dunningStages());
        self::assertSame(['projects:unlimited', 'export'], $pro->featuresPastDue(0));
        self::assertSame(['projects:unlimited', 'export'], $pro->featuresPastDue(400));
    }

    public function testTheLastStageStartedGivesTheFeatures(): void
    {
        $pro = Offers::pro();
        $stages = [DunningStage::full(0), DunningStage::reduced(3, ['export']), DunningStage::cut(10)];
        $pro->changeDunning($stages);

        self::assertEquals($stages, $pro->dunningStages());
        self::assertSame(['projects:unlimited', 'export'], $pro->featuresPastDue(2));
        self::assertSame(['export'], $pro->featuresPastDue(3));
        self::assertSame(['export'], $pro->featuresPastDue(9));
        self::assertSame([], $pro->featuresPastDue(10));
        self::assertSame([], $pro->featuresPastDue(300));

        $late = Offers::pro();
        $late->changeDunning([DunningStage::reduced(5, ['export'])]);
        self::assertSame(['projects:unlimited', 'export'], $late->featuresPastDue(4));
        self::assertSame(['export'], $late->featuresPastDue(5));

        $late->changeDunning([]);
        self::assertSame([], $late->dunningStages());
    }

    public function testStagesStartEachLaterThanTheOneBefore(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The dunning stages of the offer "pro" each start later than the one before.');

        Offers::pro()->changeDunning([DunningStage::full(3), DunningStage::cut(3)]);
    }

    public function testAReducedAccessKeepsOnlyFeaturesOfTheTier(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A reduced access of the offer "pro" keeps only features of the tier.');

        Offers::pro()->changeDunning([DunningStage::reduced(3, ['export', 'seats'])]);
    }

    public function testAnOfferHasAtMostTenStages(): void
    {
        $stages = array_map(DunningStage::full(...), range(0, 9));
        $offer = Offers::pro();
        $offer->changeDunning($stages);
        self::assertCount(10, $offer->dunningStages());

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The offer "pro" has at most ten dunning stages.');

        $offer->changeDunning([...$stages, DunningStage::cut(10)]);
    }

    public function testDaysPastDueCountFromTheFirstFailureUntilPaid(): void
    {
        $subscription = Subscription::start(Offers::free(), 'account-1', $this->at('2026-01-31 00:00'));
        self::assertNull($subscription->pastDueSince());
        self::assertNull($subscription->daysPastDue($this->at('2026-03-01 00:00')));

        $subscription->awaitPayment('R2', $this->at('2026-02-28 00:00'));
        $subscription->paymentFailed('R2', 5, $this->at('2026-02-28 06:00'));
        $subscription->paymentFailed('R2', 5, $this->at('2026-03-02 06:00'));

        self::assertEquals($this->at('2026-02-28 06:00'), $subscription->pastDueSince());
        self::assertSame(0, $subscription->daysPastDue($this->at('2026-02-28 05:00')));
        self::assertSame(0, $subscription->daysPastDue($this->at('2026-02-26 00:00')));
        self::assertSame(0, $subscription->daysPastDue($this->at('2026-03-01 05:59:59')));
        self::assertSame(0, $subscription->daysPastDue($this->at('2026-03-01 05:59')));
        self::assertSame(1, $subscription->daysPastDue($this->at('2026-03-01 06:00')));
        self::assertSame(4, $subscription->daysPastDue($this->at('2026-03-04 07:00')));

        $subscription->paid('R2', BillingPeriod::of(BillingUnit::Month), $this->at('2026-03-04 07:00'));
        self::assertNull($subscription->pastDueSince());

        $subscription->awaitPayment('R3', $this->at('2026-03-31 00:00'));
        $subscription->paymentFailed('R3', 5, $this->at('2026-04-01 00:00'));
        self::assertEquals($this->at('2026-04-01 00:00'), $subscription->pastDueSince());
    }

    public function testAnEndedSubscriptionIsNoLongerPastDue(): void
    {
        $subscription = Subscription::start(Offers::free(), 'account-1', $this->at('2026-01-31 00:00'));
        $subscription->awaitPayment('R2', $this->at('2026-02-28 00:00'));
        $subscription->paymentFailed('R2', 1, $this->at('2026-02-28 06:00'));

        self::assertNull($subscription->pastDueSince());
        self::assertNull($subscription->daysPastDue($this->at('2026-03-10 00:00')));
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
