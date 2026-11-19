<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Offer::class)]
final class OfferTest extends TestCase
{
    public function testAnOfferHoldsItsTier(): void
    {
        $offer = Offer::create('pro', 'studio', 1, 'Pro', Money::of(999, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month, 3), 14, 'digital', ['export', 'export', 'projects:unlimited']);

        self::assertSame('pro', $offer->code);
        self::assertSame('studio', $offer->family);
        self::assertSame(1, $offer->rank);
        self::assertSame('Pro', $offer->name());
        self::assertSame(999, $offer->price->minorUnits);
        self::assertSame('CAD', $offer->price->currency->code);
        self::assertTrue($offer->period->equals(BillingPeriod::of(BillingUnit::Month, 3)));
        self::assertSame(14, $offer->trialDays);
        self::assertSame('digital', $offer->taxCategory);
        self::assertSame(['export', 'projects:unlimited'], $offer->features());
        self::assertTrue($offer->isActive());
        self::assertFalse($offer->isFree());
        self::assertTrue(Offers::free()->isFree());
    }

    public function testTheBoundsOfAnOfferAreAccepted(): void
    {
        $offer = Offer::create(str_repeat('a', 64), '0', 0, 'X', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Day), 365, 'g', [str_repeat('f', 128), 'a:b.c-d_e']);

        self::assertSame(64, \strlen($offer->code));
        self::assertSame(365, $offer->trialDays);
        self::assertSame(0, $offer->rank);
        self::assertSame(0, Offer::create('x', 'y', 0, 'X', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Day), 0, 'g')->trialDays);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidOffers(): iterable
    {
        yield 'empty code' => [['code' => ''], '"" is not an offer code: up to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'uppercase code' => [['code' => 'Pro'], '"Pro" is not an offer code'];
        yield 'code too long' => [['code' => str_repeat('a', 65)], 'is not an offer code'];
        yield 'code with a trailing newline' => [['code' => "pro\n"], 'is not an offer code'];
        yield 'code starting with a dash' => [['code' => '-pro'], 'is not an offer code'];
        yield 'family with a space' => [['family' => 'my studio'], '"my studio" is not an offer family'];
        yield 'negative rank' => [['rank' => -1], 'An offer ranks from 0, and its trial lasts 0 to 365 days.'];
        yield 'negative trial' => [['trial' => -1], 'An offer ranks from 0, and its trial lasts 0 to 365 days.'];
        yield 'trial beyond a year' => [['trial' => 366], 'An offer ranks from 0, and its trial lasts 0 to 365 days.'];
        yield 'blank name' => [['name' => ' '], 'The offer "pro" has a name and a tax category.'];
        yield 'no tax category' => [['category' => ''], 'The offer "pro" has a name and a tax category.'];
        yield 'negative price' => [['price' => -1], 'The offer "pro" has a negative price.'];
        yield 'uppercase feature' => [['features' => ['Export']], '"Export" is not a feature name: up to 128 lowercase letters, digits, "_", ".", ":" or "-".'];
        yield 'feature too long' => [['features' => [str_repeat('f', 129)]], 'is not a feature name'];
        yield 'feature with a trailing newline' => [['features' => ["export\n"]], 'is not a feature name'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidOffers')]
    public function testAnInvalidOfferIsRefused(array $override, string $message): void
    {
        $values = [...['code' => 'pro', 'family' => 'studio', 'rank' => 1, 'name' => 'Pro', 'price' => 999, 'trial' => 0, 'category' => 'digital', 'features' => []], ...$override];

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        /** @var array{code: string, family: string, rank: int, name: string, price: int, trial: int, category: string, features: list<string>} $values */
        Offer::create($values['code'], $values['family'], $values['rank'], $values['name'], Money::of($values['price'], Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), $values['trial'], $values['category'], $values['features']);
    }

    public function testAnOfferIsRepricedInItsCurrency(): void
    {
        $offer = Offers::pro();
        $offer->reprice(Money::of(1_299, Currency::of('CAD')));
        self::assertSame(1_299, $offer->price->minorUnits);
        $offer->reprice(Money::zero(Currency::of('CAD')));
        self::assertTrue($offer->isFree());
    }

    public function testARepriceInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The offer "pro" is repriced in CAD, at zero or more.');

        Offers::pro()->reprice(Money::of(999, Currency::of('USD')));
    }

    public function testANegativeRepriceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        Offers::pro()->reprice(Money::of(-1, Currency::of('CAD')));
    }

    public function testAWithdrawnOfferIsNoLongerActive(): void
    {
        $offer = Offers::pro();
        $offer->withdraw();

        self::assertFalse($offer->isActive());
    }

    public function testSeatsAreCountedOrNot(): void
    {
        self::assertNull(Offers::pro()->seats);

        foreach ([1, 100_000] as $seats) {
            self::assertSame($seats, Offer::create('crew', 'studio', 3, 'Crew', Money::of(5_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', [], $seats)->seats);
        }

        foreach ([0, 100_001] as $seats) {
            try {
                Offer::create('crew', 'studio', 3, 'Crew', Money::of(5_000, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', [], $seats);
                self::fail(\sprintf('%d seats were counted.', $seats));
            } catch (InvalidValue $invalidValue) {
                self::assertSame('The offer "crew" counts 1 to 100000 seats, or none.', $invalidValue->getMessage());
            }
        }
    }
}
