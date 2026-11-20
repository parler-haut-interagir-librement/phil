<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\Exception\GiftRefused;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftState;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Gift::class)]
#[CoversClass(GiftRefused::class)]
final class GiftTest extends TestCase
{
    public function testABoughtGiftWaitsForItsOrder(): void
    {
        $at = $this->at('2026-04-01');
        $gift = Gift::buy(Offers::pro(), $this->id('buyer-1'), 3, GiftRenewal::EndsAtTerm, $at);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $gift->id);
        self::assertSame('buyer-1', $gift->buyer);
        self::assertSame('pro', $gift->offerCode);
        self::assertSame(3, $gift->periods);
        self::assertSame(GiftRenewal::EndsAtTerm, $gift->renewal);
        self::assertSame($at, $gift->createdAt);
        self::assertSame(GiftState::Pending, $gift->state());
        self::assertSame('CAD', $gift->currency()->code);
        self::assertTrue($gift->periodAmount()->isZero());
        self::assertNull($gift->codeHash());
        self::assertNull($gift->awaitedOrder());
        self::assertNull($gift->redeemableUntil());
        self::assertNull($gift->beneficiary());
        self::assertNull($gift->subscriptionId());
        self::assertNull($gift->closedAt());
        self::assertSame(1, $gift->version());
        self::assertTrue($gift->awaitsAnOrder());
    }

    public function testPeriodsAreCounted(): void
    {
        self::assertSame(36, Gift::buy(Offers::pro(), $this->id('b'), 36, GiftRenewal::BuyerPays, $this->at('2026-04-01'))->periods);
        self::assertSame(1, Gift::buy(Offers::pro(), $this->id('b'), 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'))->periods);

        foreach ([0, 37] as $periods) {
            try {
                Gift::buy(Offers::pro(), $this->id('b'), $periods, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
                self::fail(\sprintf('A gift of %d periods was bought.', $periods));
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('A gift pays 1 to 36 periods; %d does not.', $periods), $invalidValue->getMessage());
            }
        }
    }

    public function testAFreeOrWithdrawnOfferIsNotOffered(): void
    {
        $withdrawn = Offers::pro();
        $withdrawn->withdraw();

        foreach ([[Offers::free(), 'The offer "free" is free: there is nothing to offer.'], [$withdrawn, 'The offer "pro" is withdrawn: it is not offered any more.']] as [$offer, $message]) {
            try {
                Gift::buy($offer, $this->id('b'), 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
                self::fail('The gift was bought.');
            } catch (GiftRefused $giftRefused) {
                self::assertSame($message, $giftRefused->getMessage());
            }
        }
    }

    public function testACodeIsReadAsTyped(): void
    {
        $hash = hash('sha256', '0123456789ABCDEF');

        self::assertSame($hash, Gift::hashOf('0123-4567-89AB-CDEF'));
        self::assertSame($hash, Gift::hashOf('o123 4567 89ab cdef'));
        self::assertSame(hash('sha256', '1111456789ABCDEF'), Gift::hashOf('iIlL-4567-89AB-CDEF'));
        self::assertSame(hash('sha256', 'ABCDEFGHJKMNPQRS'), Gift::hashOf('ABCDEFGHJKMNPQRS'));
        self::assertSame(hash('sha256', 'TVWXYZ0123456789'), Gift::hashOf('TVWX-YZ01-2345-6789'));
        self::assertSame($hash, Gift::hashOf(str_repeat('-', 48).'0123456789ABCDEF'), '64 characters with dashes');
        self::assertNull(Gift::hashOf(str_repeat('-', 49).'0123456789ABCDEF'), 'more than 64 characters');
        self::assertNull(Gift::hashOf('0123-4567-89AB-CDE'));
        self::assertNull(Gift::hashOf('0123-4567-89AB-CDEFG'));
        self::assertNull(Gift::hashOf('0123-4567-89AB-CDEU'));
        self::assertNull(Gift::hashOf("0123-4567-89AB-CDEF\n"));
        self::assertSame(hash('sha256', 'X0123456789ABCDE'), Gift::hashOf('X012-3456-789A-BCDE'));
    }

    public function testAPaidGiftIssuesACodeThatRedeemsItOnce(): void
    {
        $gift = $this->ordered();
        $code = $gift->paid('G-1', $this->at('2026-05-01'));

        self::assertSame(GiftState::Paid, $gift->state());
        self::assertNull($gift->awaitedOrder());
        self::assertSame(Gift::hashOf($code), $gift->codeHash());
        self::assertSame(999, $gift->periodAmount()->minorUnits);
        self::assertFalse($gift->awaitsAnOrder());
        self::assertTrue($gift->isRedeemableWith($code, $this->at('2026-04-30')));
        self::assertFalse($gift->isRedeemableWith($code, $this->at('2026-05-01')));
        self::assertFalse($gift->isRedeemableWith('0000-0000-0000-0000', $this->at('2026-04-30')));
        self::assertFalse($gift->isRedeemableWith('nope', $this->at('2026-04-30')));

        $subscription = Subscription::start(Offers::free(), 'friend-1', $this->at('2026-04-10'));
        $gift->redeem($code, $this->id('friend-1'), $subscription, $this->at('2026-04-10'));

        self::assertSame(GiftState::Redeemed, $gift->state());
        self::assertSame('friend-1', $gift->beneficiary());
        self::assertSame($subscription->id, $gift->subscriptionId());
        self::assertNull($gift->codeHash());
        self::assertSame('2026-04-10', $gift->closedAt()?->format('Y-m-d'));
        self::assertFalse($gift->isRedeemableWith($code, $this->at('2026-04-10')));

        $this->expectException(GiftRefused::class);
        $this->expectExceptionMessageIsOrContains('This code redeems no gift.');

        $gift->redeem($code, $this->id('friend-2'), $subscription, $this->at('2026-04-10'));
    }

    public function testOnlyThePaidOrderIsAccepted(): void
    {
        $gift = $this->ordered();

        try {
            $gift->paid('G-2', $this->at('2026-05-01'));
            self::fail('Another order paid the gift.');
        } catch (GiftRefused $giftRefused) {
            self::assertSame(\sprintf('The gift %s awaits no payment of the order G-2.', $gift->id), $giftRefused->getMessage());
        }

        try {
            $gift->awaitPayment('G-2', Money::of(999, Currency::of('CAD')));
            self::fail('A second order was awaited.');
        } catch (GiftRefused $giftRefused) {
            self::assertSame(\sprintf('The gift %s is pending: it cannot await the payment of another order.', $gift->id), $giftRefused->getMessage());
        }

        $this->expectException(GiftRefused::class);

        $gift->paymentFailed('G-2');
    }

    public function testTheAmountIsInTheGiftsCurrencyAndNotNegative(): void
    {
        foreach ([Money::of(999, Currency::of('USD')), Money::of(-1, Currency::of('CAD'))] as $amount) {
            try {
                Gift::buy(Offers::pro(), $this->id('b'), 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'))->awaitPayment('G-1', $amount);
                self::fail('The amount was taken.');
            } catch (InvalidValue $invalidValue) {
                self::assertStringEndsWith('is paid in CAD, at zero or more.', $invalidValue->getMessage());
            }
        }

        $gift = Gift::buy(Offers::pro(), $this->id('b'), 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
        $gift->awaitPayment('G-1', Money::zero(Currency::of('CAD')));
        self::assertSame('G-1', $gift->awaitedOrder());
    }

    public function testACodeIsReplacedOnlyWhilePaid(): void
    {
        $gift = $this->ordered();

        try {
            $gift->reissueCode();
            self::fail('A code was issued before payment.');
        } catch (GiftRefused $giftRefused) {
            self::assertSame(\sprintf('The gift %s is pending: it cannot have its code replaced.', $gift->id), $giftRefused->getMessage());
        }

        $old = $gift->paid('G-1', $this->at('2026-05-01'));
        $new = $gift->reissueCode();

        self::assertNotSame($old, $new);
        self::assertSame(Gift::hashOf($new), $gift->codeHash());
    }

    public function testAGiftIsCancelledBeforeItIsRedeemed(): void
    {
        $gift = $this->ordered();

        try {
            $gift->cancel($this->at('2026-04-02'));
            self::fail('A gift awaiting its payment was cancelled.');
        } catch (GiftRefused) {
        }

        $code = $gift->paid('G-1', $this->at('2026-05-01'));
        $gift->cancel($this->at('2026-04-03'));

        self::assertSame(GiftState::Cancelled, $gift->state());
        self::assertNull($gift->codeHash());
        self::assertSame('2026-04-03', $gift->closedAt()?->format('Y-m-d'));
        self::assertFalse($gift->isRedeemableWith($code, $this->at('2026-04-04')));

        $this->expectException(GiftRefused::class);
        $this->expectExceptionMessageIsOrContains('is cancelled: it cannot be cancelled');

        $gift->cancel($this->at('2026-04-04'));
    }

    public function testARedeemedGiftIsNotCancelled(): void
    {
        $gift = $this->ordered();
        $code = $gift->paid('G-1', $this->at('2026-05-01'));
        $gift->redeem($code, $this->id('friend-1'), Subscription::start(Offers::free(), 'friend-1', $this->at('2026-04-10')), $this->at('2026-04-10'));

        $this->expectException(GiftRefused::class);

        $gift->cancel($this->at('2026-04-11'));
    }

    public function testAPendingGiftWithoutOrderIsCancelled(): void
    {
        $gift = Gift::buy(Offers::pro(), $this->id('b'), 1, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
        $gift->cancel($this->at('2026-04-02'));

        self::assertSame(GiftState::Cancelled, $gift->state());
        self::assertSame('No gift is known as x.', GiftRefused::unknown('x')->getMessage());
    }

    public function testACodeIsFourGroupsOfFour(): void
    {
        $code = $this->ordered()->paid('G-1', $this->at('2026-05-01'));

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{4}(-[0-9A-HJKMNP-TV-Z]{4}){3}$/D', $code);
    }

    private function ordered(): Gift
    {
        $gift = Gift::buy(Offers::pro(), $this->id('buyer-1'), 3, GiftRenewal::BuyerPays, $this->at('2026-04-01'));
        $gift->awaitPayment('G-1', Money::of(999, Currency::of('CAD')));

        return $gift;
    }

    private function id(string $value): StableIdentifier
    {
        return StableIdentifier::of($value, 'person');
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00:00 UTC');
    }
}
