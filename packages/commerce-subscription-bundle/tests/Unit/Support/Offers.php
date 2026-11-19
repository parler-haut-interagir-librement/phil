<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Support;

use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The offers of a family `studio`: a free tier, a monthly tier with a trial, a yearly tier without one.
 */
final class Offers
{
    public static function free(): Offer
    {
        return Offer::create('free', 'studio', 0, 'Free', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['projects:3']);
    }

    public static function pro(): Offer
    {
        return Offer::create('pro', 'studio', 1, 'Pro', Money::of(999, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 14, 'digital', ['projects:unlimited', 'export']);
    }

    public static function team(): Offer
    {
        return Offer::create('team', 'studio', 2, 'Team', Money::of(9_990, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Year), 0, 'digital', ['projects:unlimited', 'export', 'seats']);
    }
}
