<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\Gifts;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\ProrationOrderProcessor;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Exception\GiftRefused;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftState;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryGifts;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryOffers;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A buyer offers three months of `pro` (9.99 a month) to someone else, who redeems the code.
 */
#[CoversClass(Gifts::class)]
#[CoversClass(OfferSellables::class)]
final class GiftsTest extends TestCase
{
    private InMemoryOffers $offers;

    private InMemorySubscriptions $subscriptions;

    private InMemoryGifts $gifts;

    private FeatureAccess $access;

    private Gifts $service;

    protected function setUp(): void
    {
        $this->offers = new InMemoryOffers(Offers::free(), Offers::pro(), Offers::team());
        $this->subscriptions = new InMemorySubscriptions();
        $this->gifts = new InMemoryGifts();
        $this->access = new FeatureAccess($this->subscriptions, $this->offers);
        $this->service = new Gifts($this->offers, $this->gifts, $this->subscriptions, $this->modifier(), 30, $this->access);
    }

    /**
     * The order sells three periods at the offer's price, taxed by the core; the payment issues the code, which
     * starts the beneficiary's subscription for the three months paid, renewed at the buyer's charge.
     */
    public function testAGiftIsOrderedPaidAndRedeemed(): void
    {
        $gift = $this->service->buy('buyer-1', 'pro', 3, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
        self::assertSame([$gift], $this->gifts->gifts);

        $order = $this->service->order($gift, 'G-1', 'QC', $this->at('2026-04-01'));

        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame(['gift:'.$gift->id], array_map(static fn (OrderLine $line): string => $line->reference->value(), $order->lines()));
        self::assertSame(3, $order->lines()[0]->quantity);
        self::assertSame(2_997, $order->itemsTotal()->minorUnits);
        self::assertSame(2_997 + 150 + 299, $order->total()->minorUnits);
        self::assertSame('G-1', $gift->awaitedOrder());
        self::assertSame(999, $gift->periodAmount()->minorUnits);

        $code = $this->service->paid($gift, 'G-1', $this->at('2026-04-02'));

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{4}(-[0-9A-HJKMNP-TV-Z]{4}){3}$/D', $code);
        self::assertSame(GiftState::Paid, $gift->state());
        self::assertSame('2026-05-02', $gift->redeemableUntil()?->format('Y-m-d'));
        self::assertSame([], $this->access->features('friend-1'));

        $subscription = $this->service->redeem(strtolower($code), 'friend-1', $this->at('2026-04-10'));

        self::assertSame([$subscription], $this->subscriptions->subscriptions);
        self::assertSame('friend-1', $subscription->subscriber);
        self::assertSame('buyer-1', $subscription->payer());
        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame('2026-07-10', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(3, $subscription->periodsPaid());
        self::assertFalse($subscription->cancelsAtPeriodEnd());
        self::assertSame(999, $subscription->periodAmount()->minorUnits);
        self::assertSame(GiftState::Redeemed, $gift->state());
        self::assertSame('friend-1', $gift->beneficiary());
        self::assertSame($subscription->id, $gift->subscriptionId());
        self::assertSame(['projects:unlimited', 'export'], $this->access->features('friend-1'));
    }

    /**
     * Ended at its term, the subscription is cancelled at the end of the periods paid.
     */
    public function testAGiftEndingAtItsTermIsCancelledAtThePeriodEnd(): void
    {
        $code = $this->paidGift(GiftRenewal::EndsAtTerm);

        $subscription = $this->service->redeem($code, 'friend-1', $this->at('2026-04-10'));

        self::assertNull($subscription->payer());
        self::assertTrue($subscription->cancelsAtPeriodEnd());
    }

    /**
     * Unknown, malformed, used, replaced or expired: every code is refused the same way.
     */
    public function testACodeIsRefusedTheSameWayWhateverTheReason(): void
    {
        $code = $this->paidGift();
        $refusals = [];

        foreach (['0000-0000-0000-0000', 'not a code', str_repeat('A', 65)] as $wrong) {
            $refusals[] = $this->refusal($wrong, $this->at('2026-04-10'));
        }

        $refusals[] = $this->refusal($code, $this->at('2026-05-02'));
        $this->service->redeem($code, 'friend-1', $this->at('2026-04-10'));
        $refusals[] = $this->refusal($code, $this->at('2026-04-11'));

        self::assertSame(array_fill(0, 5, 'This code redeems no gift.'), $refusals);
        // The well-formed unknown code, the expired one, the redemption and the used one: a malformed code reads nothing.
        self::assertCount(4, $this->gifts->lookups);
    }

    /**
     * A lost code is replaced; the old one no longer redeems the gift.
     */
    public function testAReissuedCodeVoidsTheOldOne(): void
    {
        $code = $this->paidGift();
        $gift = $this->gifts->gifts[0];

        $new = $this->service->reissueCode($gift);

        self::assertNotSame($code, $new);
        self::assertSame('This code redeems no gift.', $this->refusal($code, $this->at('2026-04-10')));
        self::assertSame('friend-1', $this->service->redeem($new, 'friend-1', $this->at('2026-04-10'))->subscriber);
    }

    /**
     * The beneficiary is named by a stable identifier, never an email address.
     */
    public function testTheBeneficiaryIsAStableIdentifier(): void
    {
        $code = $this->paidGift();

        try {
            $this->service->redeem($code, 'friend@example.com', $this->at('2026-04-10'));
            self::fail('An email address redeemed the gift.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringContainsString('stable identifier of a beneficiary', $invalidValue->getMessage());
        }

        self::assertSame(GiftState::Paid, $this->gifts->gifts[0]->state());
        self::assertSame([], $this->subscriptions->subscriptions);
    }

    /**
     * A failed payment leaves the gift waiting for another order; a cancelled gift is not ordered again.
     */
    public function testAFailedPaymentWaitsForAnotherOrder(): void
    {
        $gift = $this->service->buy('buyer-1', 'pro', 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
        $this->service->order($gift, 'G-1', null, $this->at('2026-04-01'));

        $this->service->paymentFailed($gift, 'G-1');

        self::assertNull($gift->awaitedOrder());
        self::assertSame(999, $this->service->order($gift, 'G-2', null, $this->at('2026-04-02'))->total()->minorUnits);

        $this->service->paymentFailed($gift, 'G-2');
        $this->service->cancel($gift, $this->at('2026-04-03'));
        self::assertSame(GiftState::Cancelled, $gift->state());

        $this->expectException(GiftRefused::class);
        $this->expectExceptionMessageIsOrContains('it cannot be ordered again');

        $this->service->order($gift, 'G-3', null, $this->at('2026-04-03'));
    }

    /**
     * An offer withdrawn after the gift was paid is still redeemed; an unknown offer is refused.
     */
    public function testAWithdrawnOfferIsStillRedeemed(): void
    {
        $code = $this->paidGift();
        $this->offers->find('pro')?->withdraw();

        self::assertSame('pro', $this->service->redeem($code, 'friend-1', $this->at('2026-04-10'))->offerCode());

        $this->expectException(SubscriptionRefused::class);

        $this->service->buy('buyer-1', 'gold', 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
    }

    /**
     * Its renewals are ordered as any subscription's, for the buyer to pay.
     */
    public function testAGiftRenewsAsAnySubscription(): void
    {
        $code = $this->paidGift();
        $subscription = $this->service->redeem($code, 'friend-1', $this->at('2026-04-10'));
        $subscriptions = new Subscriptions($this->offers, $this->subscriptions, $this->modifier(), 2);

        self::assertSame([$subscription], $subscriptions->sweep($this->at('2026-07-10')));
        self::assertSame(999, $subscriptions->order($subscription, 'R-1', null, $this->at('2026-07-10'))->total()->minorUnits);
    }

    /**
     * Without a gift store, the resolver does not sell gifts.
     */
    public function testGiftsAreSoldOnlyWithAStore(): void
    {
        self::assertFalse(new OfferSellables($this->offers, $this->subscriptions)->supports(OfferSellables::GIFT_TYPE));
        self::assertTrue(new OfferSellables($this->offers, $this->subscriptions, $this->gifts)->supports(OfferSellables::GIFT_TYPE));
    }

    /**
     * Without being told, a paid gift is redeemable for a year.
     */
    public function testAGiftIsRedeemableForAYearByDefault(): void
    {
        $gifts = new Gifts($this->offers, $this->gifts, $this->subscriptions, $this->modifier());
        $gift = $gifts->buy('buyer-1', 'pro', 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
        $gifts->order($gift, 'G-1', null, $this->at('2026-04-01'));
        $code = $gifts->paid($gift, 'G-1', $this->at('2026-04-02'));

        self::assertSame('2027-04-02', $gift->redeemableUntil()?->format('Y-m-d'));
        self::assertSame('friend-1', $gifts->redeem($code, 'friend-1', $this->at('2026-04-03'))->subscriber);
    }

    /**
     * A gift unknown, already ordered, or asked of a resolver without a store, sells nothing.
     */
    public function testOnlyAGiftAwaitingItsOrderIsSold(): void
    {
        $gift = $this->service->buy('buyer-1', 'pro', 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
        $sellables = new OfferSellables($this->offers, $this->subscriptions, $this->gifts);

        self::assertSame('Pro', $sellables->resolve(SellableReference::of(OfferSellables::GIFT_TYPE, $gift->id))?->description);
        self::assertNull($sellables->resolve(SellableReference::of(OfferSellables::GIFT_TYPE, 'gone')));
        self::assertNull(new OfferSellables($this->offers, $this->subscriptions)->resolve(SellableReference::of(OfferSellables::GIFT_TYPE, $gift->id)));

        $this->service->order($gift, 'G-1', null, $this->at('2026-04-01'));
        self::assertNull($sellables->resolve(SellableReference::of(OfferSellables::GIFT_TYPE, $gift->id)));
    }

    private function paidGift(GiftRenewal $renewal = GiftRenewal::BuyerPays): string
    {
        $gift = $this->service->buy('buyer-1', 'pro', 3, $renewal, $this->at('2026-04-01'));
        $this->service->order($gift, 'G-1', null, $this->at('2026-04-01'));

        return $this->service->paid($gift, 'G-1', $this->at('2026-04-02'));
    }

    private function refusal(string $code, DateTimeImmutable $at): string
    {
        try {
            $this->service->redeem($code, 'friend-1', $at);
        } catch (GiftRefused $giftRefused) {
            return $giftRefused->getMessage();
        }

        return 'redeemed';
    }

    private function modifier(): OrderModifier
    {
        $sellables = new OfferSellables($this->offers, $this->subscriptions, $this->gifts);

        return new OrderModifier([$sellables], [], [new ProrationOrderProcessor($this->subscriptions), new TaxOrderProcessor(new ConfiguredTaxRates(['digital' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]))]);
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00:00 UTC');
    }
}
