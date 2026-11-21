<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceStoredValueBundle\Domain\Exception\StoredValueRefused;
use Phil\CommerceStoredValueBundle\Domain\Instrument\GiftCardOrigin;
use Phil\CommerceStoredValueBundle\Domain\Instrument\InstrumentKind;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Instrument::class)]
#[CoversClass(Movement::class)]
#[CoversClass(MovementKind::class)]
#[CoversClass(StoredValueRefused::class)]
final class InstrumentTest extends TestCase
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function testAGiftCardIsIssuedWithItsValue(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $code = GiftCardCode::fromString('7KQ2-M9XA-3TFD-W8HC');
        [$card, $issued] = Instrument::giftCard($code, self::cad(5_000), $at, null, 'A-1');

        self::assertMatchesRegularExpression(self::UUID, $card->id);
        self::assertSame([InstrumentKind::GiftCard, $code->hash(), 'W8HC', null, 5_000, 'CAD', $at, null, true, 1], [
            $card->kind, $card->codeHash, $card->lastFour, $card->holder, $card->balance->minorUnits, $card->balance->currency->code, $card->openedAt, $card->expiresAt, $card->isActive(), $card->version(),
        ]);
        self::assertSame([GiftCardOrigin::Paid, false], [$card->origin, $card->specificService]);
        self::assertMatchesRegularExpression(self::UUID, $issued->id);
        self::assertSame([$card->id, MovementKind::Issued, 5_000, 5_000, 'CAD', $at, 'A-1', null, null], [
            $issued->instrument, $issued->kind, $issued->amount->minorUnits, $issued->balanceAfter->minorUnits, $issued->amount->currency->code, $issued->occurredAt, $issued->orderNumber, $issued->payment, $issued->note,
        ]);
        self::assertSame([null, null], [$issued->issuedUnit, $issued->idempotencyKey]);
    }

    public function testAnOrdersCardIsIssuedUnderItsRank(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        [, $first] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, null, 'A-1', issuedUnit: 0);
        [, $third] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, null, 'A-1', issuedUnit: 2);

        self::assertSame([0, 2], [$first->issuedUnit, $third->issuedUnit]);
    }

    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function ranksRefused(): iterable
    {
        yield 'no order' => [null, 0];
        yield 'below zero' => ['A-1', -1];
    }

    #[DataProvider('ranksRefused')]
    public function testARankBelongsToAnOrder(?string $orderNumber, int $rank): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A gift card is issued under a rank, from 0, among the cards of its order only.');

        Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), new DateTimeImmutable(), null, $orderNumber, issuedUnit: $rank);
    }

    public function testACreditKeepsItsIdempotencyKey(): void
    {
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable());

        $movement = $credit->credit(MovementKind::Granted, self::cad(100), new DateTimeImmutable(), idempotencyKey: 'return-1');

        self::assertSame(['return-1', null], [$movement->idempotencyKey, $movement->issuedUnit]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keysRefused(): iterable
    {
        yield 'empty' => [''];
        yield 'padded before' => [' key'];
        yield 'padded after' => ["key\n"];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'too long in characters' => [str_repeat('é', 129)];
    }

    #[DataProvider('keysRefused')]
    public function testAKeyAMovementCannotKeepIsRefusedBeforeAnyValueIsAdded(string $key): void
    {
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable());

        try {
            $credit->credit(MovementKind::Granted, self::cad(100), new DateTimeImmutable(), idempotencyKey: $key);
            self::fail('The key is refused.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('An idempotency key is 1 to 128 characters, without surrounding spaces.', $invalidValue->getMessage());
        }

        self::assertSame(0, $credit->balance->minorUnits);

        $this->expectException(InvalidValue::class);

        Instrument::checkIdempotencyKey($key);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function keysKept(): iterable
    {
        yield 'none' => [null];
        yield 'one character' => ['k'];
        yield 'inside spaces' => ['a b'];
        yield 'the longest' => [str_repeat('a', 128)];
        yield 'the longest in characters' => [str_repeat('é', 128)];
    }

    #[DataProvider('keysKept')]
    public function testAKeyAMovementCanKeepIsAccepted(?string $key): void
    {
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable());

        $movement = $credit->credit(MovementKind::Granted, self::cad(100), new DateTimeImmutable(), idempotencyKey: $key);

        self::assertSame($key, $movement->idempotencyKey);
        self::assertSame(100, $credit->balance->minorUnits);
        Instrument::checkIdempotencyKey($key);
    }

    public function testAnIssuedCardIsCheckedLikeAnyCredit(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A stored value moves by a positive amount.');

        Instrument::giftCard(GiftCardCode::generate(), self::cad(0), new DateTimeImmutable(), null, 'A-1', issuedUnit: 0);
    }

    public function testAGiftCardMayExpire(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $expires = $at->modify('+1 year');
        [$card] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, $expires, null, GiftCardOrigin::Given);

        self::assertSame($expires, $card->expiresAt);
        self::assertFalse($card->hasExpired($expires->modify('-1 second')));
        self::assertTrue($card->hasExpired($expires));

        $this->expectException(StoredValueRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The stored value %s has expired.', $card->id));

        $card->redeem(self::cad(1), $expires, 'A-1', 'p-1');
    }

    public function testValueIsTakenByAnActiveInstrumentThatHasNotExpired(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $expires = $at->modify('+1 year');
        [$card] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, $expires, null, GiftCardOrigin::Given);
        [$voided] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, $expires, null, GiftCardOrigin::Given);
        $voided->void($at);

        self::assertSame([true, false, false], [$card->canTakeValue($at), $card->canTakeValue($expires), $voided->canTakeValue($at)]);
    }

    public function testAGiftCardExpiresAfterItIsIssued(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A gift card expires after it is issued.');

        Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, $at, null, GiftCardOrigin::Given);
    }

    public function testAPaidCardDoesNotExpire(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A paid gift card does not expire, unless it is for a specific service.');

        Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, $at->modify('+5 years'));
    }

    public function testAPaidCardForASpecificServiceMayExpire(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        [$card] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at, $at->modify('+1 year'), 'A-1', GiftCardOrigin::Paid, true);

        self::assertSame([GiftCardOrigin::Paid, true, '2027-11-03'], [$card->origin, $card->specificService, $card->expiresAt?->format('Y-m-d')]);
    }

    public function testAGivenCardIsKnownAsGiven(): void
    {
        [$card] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), new DateTimeImmutable(), null, null, GiftCardOrigin::Given);

        self::assertSame([GiftCardOrigin::Given, false, null], [$card->origin, $card->specificService, $card->expiresAt]);
    }

    public function testACardWithoutExpiryNeverExpires(): void
    {
        [$card] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), new DateTimeImmutable());

        self::assertFalse($card->hasExpired(new DateTimeImmutable('+100 years')));
    }

    public function testAStoreCreditIsOpenedEmpty(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $credit = Instrument::storeCredit(str_repeat('é', 255), Currency::of('CAD'), $at);

        self::assertSame([InstrumentKind::StoreCredit, null, null, str_repeat('é', 255), 0, 'CAD', $at, null, null, false], [
            $credit->kind, $credit->codeHash, $credit->lastFour, $credit->holder, $credit->balance->minorUnits, $credit->balance->currency->code, $credit->openedAt, $credit->expiresAt, $credit->origin, $credit->specificService,
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badHolders(): iterable
    {
        yield 'blank' => [' '];
        yield 'too long' => [str_repeat('é', 256)];
    }

    #[DataProvider('badHolders')]
    public function testAStoreCreditBelongsToACustomer(string $holder): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A store credit belongs to a customer, named in 1 to 255 characters.');

        Instrument::storeCredit($holder, Currency::of('CAD'), new DateTimeImmutable());
    }

    public function testValueIsAddedAndSpent(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), $at);

        $granted = $credit->credit(MovementKind::Granted, self::cad(3_000), $at, 'A-1', null, str_repeat('é', 300));
        $redeemed = $credit->redeem(self::cad(1_200), $at, 'A-2', 'p-1');
        $restored = $credit->credit(MovementKind::Restored, self::cad(200), $at, 'A-2', 'p-1');

        self::assertSame([3_000, 3_000, str_repeat('é', 255)], [$granted->amount->minorUnits, $granted->balanceAfter->minorUnits, $granted->note]);
        self::assertSame([MovementKind::Redeemed, -1_200, 1_800, 'A-2', 'p-1'], [$redeemed->kind, $redeemed->amount->minorUnits, $redeemed->balanceAfter->minorUnits, $redeemed->orderNumber, $redeemed->payment]);
        self::assertSame([200, 2_000], [$restored->amount->minorUnits, $restored->balanceAfter->minorUnits]);
        self::assertSame(2_000, $credit->balance->minorUnits);
        self::assertNotSame($granted->id, $redeemed->id);
    }

    public function testTheWholeBalanceMaybeSpent(): void
    {
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable());
        $credit->credit(MovementKind::Granted, self::cad(500), new DateTimeImmutable());

        // A payment checks the amount before it exists, without changing the balance.
        $credit->assertRedeemable(self::cad(500), new DateTimeImmutable());
        self::assertSame(500, $credit->balance->minorUnits);
        self::assertSame(0, $credit->redeem(self::cad(500), new DateTimeImmutable(), 'A-1', 'p-1')->balanceAfter->minorUnits);
    }

    public function testMoreThanTheBalanceIsRefused(): void
    {
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable());
        $credit->credit(MovementKind::Granted, self::cad(500), new DateTimeImmutable());

        $this->expectException(StoredValueRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The stored value %s holds 5.00; 5.01 is more.', $credit->id));

        $credit->redeem(self::cad(501), new DateTimeImmutable(), 'A-1', 'p-1');
    }

    /**
     * @return iterable<string, array{Money, string}>
     */
    public static function badAmounts(): iterable
    {
        yield 'zero' => [self::cad(0), 'A stored value moves by a positive amount.'];
        yield 'negative' => [self::cad(-1), 'A stored value moves by a positive amount.'];
        yield 'another currency' => [Money::of(1, Currency::of('USD')), 'is in CAD, not in USD.'];
    }

    #[DataProvider('badAmounts')]
    public function testABalanceMovesByAPositiveAmountInItsCurrency(Money $amount, string $message): void
    {
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable());

        try {
            $credit->redeem($amount, new DateTimeImmutable(), 'A-1', 'p-1');
            self::fail('A bad redemption was accepted.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringContainsString($message, $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $credit->credit(MovementKind::Granted, $amount, new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{MovementKind}>
     */
    public static function debits(): iterable
    {
        yield 'redeemed' => [MovementKind::Redeemed];
        yield 'voided' => [MovementKind::Voided];
    }

    #[DataProvider('debits')]
    public function testACreditAddsValue(MovementKind $kind): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A "%s" movement takes value away.', $kind->value));

        Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable())->credit($kind, self::cad(1), new DateTimeImmutable());
    }

    public function testWhichMovementsAddValue(): void
    {
        self::assertSame([true, true, true, false, true, false], array_map(static fn (MovementKind $kind): bool => $kind->credits(), MovementKind::cases()));
    }

    public function testAVoidedInstrumentLosesItsValueForGood(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        [$card] = Instrument::giftCard(GiftCardCode::generate(), self::cad(5_000), $at);

        $voided = $card->void($at, 'Lost');

        self::assertSame([MovementKind::Voided, -5_000, 0, 'Lost', null, null], [$voided->kind, $voided->amount->minorUnits, $voided->balanceAfter->minorUnits, $voided->note, $voided->orderNumber, $voided->payment]);
        self::assertFalse($card->isActive());
        self::assertSame(0, $card->balance->minorUnits);

        foreach ([static fn () => $card->credit(MovementKind::Loaded, self::cad(1), $at), static fn () => $card->redeem(self::cad(1), $at, 'A-1', 'p-1'), static fn () => $card->void($at)] as $operation) {
            try {
                $operation();
                self::fail('A voided card was used.');
            } catch (StoredValueRefused $refused) {
                self::assertSame(\sprintf('The stored value %s is voided.', $card->id), $refused->getMessage());
            }
        }
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame([
            'No gift card is known by that code.',
            'ada has no store credit in CAD.',
            'The payment p-1 spent no stored value.',
        ], [
            StoredValueRefused::unknownCard()->getMessage(),
            StoredValueRefused::noCredit('ada', 'CAD')->getMessage(),
            StoredValueRefused::notRedeemed('p-1')->getMessage(),
        ]);
        self::assertInstanceOf(DomainError::class, StoredValueRefused::unknownCard());
    }

    private static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
