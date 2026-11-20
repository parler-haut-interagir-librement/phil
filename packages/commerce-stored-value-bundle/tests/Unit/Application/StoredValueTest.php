<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceStoredValueBundle\Application\GiftCardIssuance;
use Phil\CommerceStoredValueBundle\Application\GiftCards;
use Phil\CommerceStoredValueBundle\Application\GiftCardSellables;
use Phil\CommerceStoredValueBundle\Application\IssuedGiftCard;
use Phil\CommerceStoredValueBundle\Application\Port\StoreCreditHolders;
use Phil\CommerceStoredValueBundle\Application\Replays;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\CommerceStoredValueBundle\Application\StoredValueGateway;
use Phil\CommerceStoredValueBundle\Application\StoredValuePayments;
use Phil\CommerceStoredValueBundle\Domain\Exception\StoredValueRefused;
use Phil\CommerceStoredValueBundle\Domain\Instrument\GiftCardOrigin;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Tests\Unit\Support\InMemoryInstruments;
use Phil\CommerceStoredValueBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceStoredValueBundle\Tests\Unit\Support\InstrumentsView;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(GiftCards::class)]
#[CoversClass(IssuedGiftCard::class)]
#[CoversClass(GiftCardIssuance::class)]
#[CoversClass(Replays::class)]
#[CoversClass(GiftCardSellables::class)]
#[CoversClass(StoreCredits::class)]
#[CoversClass(StoredValuePayments::class)]
#[CoversClass(StoredValueGateway::class)]
final class StoredValueTest extends TestCase
{
    private InMemoryInstruments $instruments;

    private InMemoryPayments $payments;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->instruments = new InMemoryInstruments();
        $this->payments = new InMemoryPayments();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testAGivenCardMayExpireAndAPaidOneMayNot(): void
    {
        $given = $this->cards()->give($this->cad(1_000), $this->at, $this->at->modify('+90 days'));

        self::assertSame([GiftCardOrigin::Given, false, '2027-02-01', null], [$given->card->origin, $given->card->specificService, $given->card->expiresAt?->format('Y-m-d'), $this->instruments->movementsOf($given->card->id)[0]->orderNumber]);
        self::assertNull($this->cards()->give($this->cad(1_000), $this->at)->card->expiresAt);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A paid gift card does not expire, unless it is for a specific service.');

        $this->cards()->issue($this->cad(1_000), $this->at, $this->at->modify('+1 year'));
    }

    public function testAGiftCardIsIssuedLoadedAndVoided(): void
    {
        $cards = $this->cards();

        $issued = $cards->issue($this->cad(2_500), $this->at, $this->at->modify('+1 year'), 'A-1', GiftCardOrigin::Paid, true);
        $code = $issued->code->toString();

        self::assertSame($issued->card, $cards->find(strtolower($code)));
        self::assertSame(['2027-11-03 10:00:00', 2_500, GiftCardOrigin::Paid, true], [$issued->card->expiresAt?->format('Y-m-d H:i:s'), $issued->card->balance->minorUnits, $issued->card->origin, $issued->card->specificService]);
        self::assertSame(3_000, $cards->load($code, $this->cad(500), $this->at, 'Top-up')->balance->minorUnits);
        self::assertFalse($cards->void($code, $this->at, 'Stolen')->isActive());
        self::assertSame([[MovementKind::Issued, 'A-1', null], [MovementKind::Loaded, null, 'Top-up'], [MovementKind::Voided, null, 'Stolen']], array_map(static fn (Movement $movement): array => [$movement->kind, $movement->orderNumber, $movement->note], $this->instruments->movementsOf($issued->card->id)));
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $this->expectException(StoredValueRefused::class);
        $this->expectExceptionMessageIsOrContains('No gift card is known by that code.');

        $this->cards()->find('7KQ2-M9XA-3TFD-W8HC');
    }

    public function testAGiftCardIsSoldAtItsValue(): void
    {
        $sellables = new GiftCardSellables('Carte-cadeau', 'exempt', 10_000);
        $reference = GiftCardSellables::reference($this->cad(5_000));

        self::assertSame('gift_card:CAD:5000', $reference->value());
        self::assertTrue($sellables->supports('gift_card'));
        self::assertFalse($sellables->supports('product'));
        $sellable = $sellables->resolve($reference);
        self::assertSame(['Carte-cadeau CAD 50.00', 5_000, 'CAD', 'exempt'], [$sellable?->description, $sellable?->unitPrice->minorUnits, $sellable?->unitPrice->currency->code, $sellable?->taxCategory]);
        self::assertSame(10_000, $sellables->resolve(SellableReference::of('gift_card', 'CAD:10000'))?->unitPrice->minorUnits);

        foreach (['CAD:10001', 'CAD:0', 'CAD:050', 'XYZ:100', 'cad:100', 'CAD100', 'CAD:1000000000000000000', 'xCAD:100', 'CAD:100x'] as $id) {
            self::assertNull($sellables->resolve(SellableReference::of('gift_card', $id)), $id);
        }

        self::assertNull($sellables->resolve(SellableReference::of('product', 'CAD:100')));
        self::assertNull(GiftCardSellables::valueOf(SellableReference::of('product', 'CAD:100')));
        self::assertSame(999_999_999_999_999_999, GiftCardSellables::valueOf(SellableReference::of('gift_card', 'CAD:999999999999999999'))?->minorUnits);
    }

    public function testAnOrdersGiftCardsAreIssuedOnce(): void
    {
        $cards = $this->cards();
        $order = $this->order([['gift_card', 'CAD:5000', 2, 4_000], ['product', 'BOOK', 1, 1_500], ['gift_card', 'CAD:2000', 1, 2_000]]);
        new StoreCredits($this->instruments)->grant('ada', $this->cad(11_500), $this->at);
        $this->stored()->payWithStoreCredit($order, 'ada', $this->at);

        $issuance = $cards->issueFor($order, $this->at);
        $issued = $issuance->issued;

        self::assertSame([5_000, 5_000, 2_000], array_map(static fn (IssuedGiftCard $card): int => $card->card->balance->minorUnits, $issued));
        self::assertSame([[GiftCardOrigin::Paid, false, null]], array_values(array_unique(array_map(static fn (IssuedGiftCard $card): array => [$card->card->origin, $card->card->specificService, $card->card->expiresAt], $issued), \SORT_REGULAR)));
        self::assertSame([0, 1, 2], array_map(fn (IssuedGiftCard $card): ?int => $this->instruments->movementsOf($card->card->id)[0]->issuedUnit, $issued));
        self::assertSame(array_map(static fn (IssuedGiftCard $card): Instrument => $card->card, $issued), $issuance->cards);
        self::assertSame($issued, iterator_to_array($issuance));
        self::assertSame([3, false], [\count($issuance), $issuance->isEmpty()]);
        self::assertSame(3, $this->instruments->issuedFor('A-1'));

        $again = $cards->issueFor($order, $this->at);

        self::assertSame([[], 0, true], [$again->issued, \count($again), $again->isEmpty()]);
        self::assertSame($issuance->cards, $again->cards, 'a second call finds the cards of the first');
        self::assertCount(4, $this->instruments->instruments);
    }

    public function testACallThatComesSecondFindsTheCardsOfTheFirst(): void
    {
        $order = $this->order([['gift_card', 'CAD:5000', 2, 5_000]]);
        new StoreCredits($this->instruments)->grant('ada', $this->cad(10_000), $this->at);
        $this->stored()->payWithStoreCredit($order, 'ada', $this->at);
        // The second request counted the cards before the first stored its own.
        $stale = new class($this->instruments) extends InstrumentsView {
            #[Override]
            public function issuedFor(string $orderNumber): int
            {
                return 0;
            }
        };
        $first = $this->cards()->issueFor($order, $this->at);

        $second = new GiftCards($stale, $this->corePayments())->issueFor($order, $this->at);

        self::assertCount(2, $first);
        self::assertSame([], $second->issued);
        self::assertSame($first->cards, $second->cards);
        self::assertCount(3, $this->instruments->instruments, 'two cards and the store credit');
        self::assertSame(2, $this->instruments->issuedFor('A-1'));
    }

    public function testTheCardsLeftToIssueTakeTheRanksThatFollow(): void
    {
        $order = $this->order([['gift_card', 'CAD:5000', 2, 5_000]]);
        new StoreCredits($this->instruments)->grant('ada', $this->cad(10_000), $this->at);
        $this->stored()->payWithStoreCredit($order, 'ada', $this->at);
        $counter = $this->cards()->issue($this->cad(5_000), $this->at, null, 'A-1');

        $issuance = $this->cards()->issueFor($order, $this->at);

        self::assertCount(1, $issuance);
        self::assertSame(1, $this->instruments->movementsOf($issuance->issued[0]->card->id)[0]->issuedUnit);
        self::assertNull($this->instruments->movementsOf($counter->card->id)[0]->issuedUnit);
        self::assertSame([$counter->card, $issuance->issued[0]->card], $issuance->cards);
    }

    public function testAnOrderWithoutGiftCardsIssuesNone(): void
    {
        $order = $this->order([['product', 'BOOK', 1, 1_500]]);
        new StoreCredits($this->instruments)->grant('ada', $this->cad(1_500), $this->at);
        $this->stored()->payWithStoreCredit($order, 'ada', $this->at);

        $issuance = $this->cards()->issueFor($order, $this->at);

        self::assertSame([[], []], [$issuance->issued, $issuance->cards]);
        self::assertTrue($issuance->isEmpty());
    }

    public function testALoadUnderAnIdempotencyKeyIsMadeOnce(): void
    {
        $cards = $this->cards();
        $code = $cards->issue($this->cad(1_000), $this->at)->code->toString();

        $cards->load($code, $this->cad(500), $this->at, 'Top-up', idempotencyKey: 'message-1');
        $replayed = $cards->load($code, $this->cad(500), $this->at, 'Top-up', idempotencyKey: 'message-1');

        self::assertSame(1_500, $replayed->balance->minorUnits);
        self::assertSame([[MovementKind::Issued, null], [MovementKind::Loaded, 'message-1']], array_map(static fn (Movement $movement): array => [$movement->kind, $movement->idempotencyKey], $this->instruments->movementsOf($replayed->id)));

        $cards->load($code, $this->cad(500), $this->at, idempotencyKey: 'message-2');
        $cards->load($code, $this->cad(500), $this->at);
        $cards->load($code, $this->cad(500), $this->at);

        self::assertSame(3_000, $cards->find($code)->balance->minorUnits);
        self::assertCount(5, $this->instruments->movementsOf($replayed->id));
    }

    public function testAKeyIsKeptPerInstrument(): void
    {
        $cards = $this->cards();
        $first = $cards->issue($this->cad(1_000), $this->at)->code->toString();
        $second = $cards->issue($this->cad(1_000), $this->at)->code->toString();

        $cards->load($first, $this->cad(500), $this->at, idempotencyKey: 'message-1');
        $cards->load($second, $this->cad(500), $this->at, idempotencyKey: 'message-1');

        self::assertSame([1_500, 1_500], [$cards->find($first)->balance->minorUnits, $cards->find($second)->balance->minorUnits]);
    }

    public function testAKeyUsedForAnotherAmountIsRefused(): void
    {
        $cards = $this->cards();
        $code = $cards->issue($this->cad(1_000), $this->at)->code->toString();
        $cards->load($code, $this->cad(500), $this->at, idempotencyKey: 'message-1');

        try {
            $cards->load($code, $this->cad(600), $this->at, idempotencyKey: 'message-1');
            self::fail('A key used for another amount is refused.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('That idempotency key was used for another amount.', $invalidValue->getMessage());
        }

        self::assertSame(1_500, $cards->find($code)->balance->minorUnits);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'padded before' => [' key'];
        yield 'padded after' => ["key\n"];
        yield 'too long' => [str_repeat('é', 129)];
    }

    #[DataProvider('notKeys')]
    public function testAKeyAMovementCannotKeepIsRefused(string $key): void
    {
        $credits = new StoreCredits($this->instruments);

        try {
            $credits->grant('ada', $this->cad(500), $this->at, idempotencyKey: $key);
            self::fail('The key is refused.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('An idempotency key is 1 to 128 characters, without surrounding spaces.', $invalidValue->getMessage());
        }

        self::assertSame([], $this->instruments->instruments, 'nothing is opened for a key refused');
        $this->expectException(InvalidValue::class);
        $this->cards()->load('7KQ2-M9XA-3TFD-W8HC', $this->cad(500), $this->at, idempotencyKey: $key);
    }

    public function testAKeyOfTheLongestLengthIsKept(): void
    {
        $movement = new StoreCredits($this->instruments)->grant('ada', $this->cad(500), $this->at, idempotencyKey: str_repeat('é', 128));

        self::assertSame(str_repeat('é', 128), $movement->idempotencyKey);
    }

    public function testAGrantUnderAnIdempotencyKeyIsMadeOnce(): void
    {
        $credits = new StoreCredits($this->instruments);

        $first = $credits->grant('ada', $this->cad(1_000), $this->at, 'Return', 'A-1', 'return-1');
        $replayed = $credits->grant('ada', $this->cad(1_000), $this->at, 'Return', 'A-1', 'return-1');

        self::assertSame($first, $replayed);
        self::assertSame(1_000, $credits->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertCount(1, $this->instruments->movementsOf($first->instrument));

        $other = $credits->grant('ada', $this->cad(1_000), $this->at, idempotencyKey: 'return-2');
        $credits->grant('ada', $this->cad(1_000), $this->at);
        $credits->grant('ada', $this->cad(1_000), $this->at);

        self::assertNotSame($first, $other);
        self::assertSame(4_000, $credits->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertCount(4, $this->instruments->movementsOf($first->instrument));
        self::assertSame(1_000, $credits->grant('bob', $this->cad(1_000), $this->at, idempotencyKey: 'return-1')->balanceAfter->minorUnits, 'a key is kept per instrument');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('That idempotency key was used for another amount.');
        $credits->grant('ada', $this->cad(999), $this->at, idempotencyKey: 'return-1');
    }

    public function testOnlyAPlacedOrderIssuesItsCards(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The order A-9 is not placed: its gift cards are not issued.');

        $this->cards()->issueFor(Order::open('A-9', Currency::of('CAD')), $this->at);
    }

    public function testAnOrderNotFullyPaidIssuesNoCard(): void
    {
        $order = $this->order([['gift_card', 'CAD:5000', 1, 5_000]]);
        new StoreCredits($this->instruments)->grant('ada', $this->cad(4_999), $this->at);
        $this->stored()->payWithStoreCredit($order, 'ada', $this->at);

        try {
            $this->cards()->issueFor($order, $this->at);
            self::fail('Cards were issued for an order not paid.');
        } catch (StoredValueRefused $storedValueRefused) {
            self::assertSame('The order A-1 still has 0.01 to pay: its gift cards are issued once it is paid.', $storedValueRefused->getMessage());
        }

        self::assertSame(0, $this->instruments->issuedFor('A-1'));
    }

    public function testACancelledOrdersCardsAreVoided(): void
    {
        $order = $this->order([['gift_card', 'CAD:5000', 2, 5_000]]);
        new StoreCredits($this->instruments)->grant('ada', $this->cad(10_000), $this->at);
        $this->stored()->payWithStoreCredit($order, 'ada', $this->at);
        [$first, $second] = $this->cards()->issueFor($order, $this->at)->issued;
        $this->cards()->void($second->code->toString(), $this->at);
        $this->cards()->issue($this->cad(1_000), $this->at, null, 'A-2');

        $voided = $this->cards()->voidFor('A-1', $this->at, 'Order cancelled');

        self::assertSame([$first->card], $voided);
        self::assertSame([false, 0], [$first->card->isActive(), $first->card->balance->minorUnits]);
        $last = $this->instruments->movementsOf($first->card->id)[1];
        self::assertSame([MovementKind::Voided, -5_000, 'Order cancelled'], [$last->kind, $last->amount->minorUnits, $last->note]);
        self::assertSame([], $this->cards()->voidFor('A-1', $this->at));
        self::assertSame([], $this->cards()->voidFor('A-9', $this->at));
    }

    public function testEveryActiveCardOfACancelledOrderIsVoided(): void
    {
        $order = $this->order([['gift_card', 'CAD:5000', 2, 5_000]]);
        new StoreCredits($this->instruments)->grant('ada', $this->cad(10_000), $this->at);
        $this->stored()->payWithStoreCredit($order, 'ada', $this->at);
        [$first, $second] = $this->cards()->issueFor($order, $this->at)->issued;

        self::assertSame([$first->card, $second->card], $this->cards()->voidFor('A-1', $this->at));
        self::assertSame([false, false], [$first->card->isActive(), $second->card->isActive()]);
    }

    public function testStoreCreditIsGrantedPerCustomerAndCurrency(): void
    {
        $credits = new StoreCredits($this->instruments);

        self::assertSame(0, $credits->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertNull(new StoreCredits($this->instruments)->of('ada', Currency::of('CAD')));

        $first = $credits->grant('ada', $this->cad(1_000), $this->at, 'Return', 'A-1');
        $credits->grant('ada', $this->cad(500), $this->at);
        $credits->grant('ada', Money::of(700, Currency::of('USD')), $this->at);

        self::assertSame([MovementKind::Granted, 'Return', 'A-1'], [$first->kind, $first->note, $first->orderNumber]);
        self::assertSame(1_500, $credits->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertSame(700, $credits->balance('ada', Currency::of('USD'))->minorUnits);
        self::assertSame('ada', $credits->of('ada', Currency::of('CAD'))?->holder);
        self::assertCount(2, $this->instruments->instruments);
        self::assertCount(3, $this->instruments->movements);
    }

    public function testAGiftCardPaysWhatItCan(): void
    {
        $issued = $this->cards()->issue($this->cad(3_000), $this->at);
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);

        $payment = $this->stored()->payWithGiftCard($order, $issued->code->toString(), $this->at);

        self::assertSame(['gift_card', 3_000, PaymentState::Completed, $payment->id], [$payment->method, $payment->amount->minorUnits, $payment->state(), $payment->externalReference()]);
        self::assertSame(0, $issued->card->balance->minorUnits);
        $redemption = $this->instruments->redemptionOf($payment->id);
        self::assertSame([$issued->card->id, -3_000, 'A-1'], [$redemption?->instrument, $redemption?->amount->minorUnits, $redemption?->orderNumber]);
        self::assertSame(2_000, $this->corePayments()->summary($order)->payable()->minorUnits);
    }

    public function testAGiftCardPaysNoMoreThanWhatIsLeft(): void
    {
        $issued = $this->cards()->issue($this->cad(9_000), $this->at);
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);

        $payment = $this->stored()->payWithGiftCard($order, $issued->code->toString(), $this->at);

        self::assertSame([5_000, 4_000], [$payment->amount->minorUnits, $issued->card->balance->minorUnits]);
    }

    public function testAnAmountMayBeChosen(): void
    {
        $issued = $this->cards()->issue($this->cad(9_000), $this->at);
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);

        self::assertSame(1_000, $this->stored()->payWithGiftCard($order, $issued->code->toString(), $this->at, $this->cad(1_000))->amount->minorUnits);
        self::assertSame(8_000, $this->stored()->payWithGiftCard($this->order([['product', 'BOOK', 1, 9_000]], 'A-2'), $issued->code->toString(), $this->at, $this->cad(8_000))->amount->minorUnits);
        self::assertSame(0, $issued->card->balance->minorUnits);
    }

    public function testMoreThanTheCardHoldsLeavesNothingBehind(): void
    {
        $issued = $this->cards()->issue($this->cad(1_000), $this->at);
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);

        try {
            $this->stored()->payWithGiftCard($order, $issued->code->toString(), $this->at, $this->cad(1_001));
            self::fail('A card paid more than it holds.');
        } catch (StoredValueRefused $storedValueRefused) {
            self::assertStringContainsString('holds 10.00; 10.01 is more.', $storedValueRefused->getMessage());
        }

        self::assertSame([], $this->payments->payments);
        self::assertSame(1_000, $issued->card->balance->minorUnits);
    }

    /**
     * A voided, expired or empty card is refused before the core's payment is created.
     */
    public function testAnUnusableCardLeavesNoPaymentBehind(): void
    {
        $voided = $this->cards()->issue($this->cad(1_000), $this->at);
        $this->cards()->void($voided->code->toString(), $this->at);
        $expired = $this->cards()->give($this->cad(1_000), $this->at->modify('-2 days'), $this->at->modify('-1 day'));
        $empty = $this->cards()->issue($this->cad(1_000), $this->at);
        $empty->card->redeem($this->cad(1_000), $this->at, 'A-0', 'p-0');

        $order = $this->order([['product', 'BOOK', 1, 5_000]]);

        foreach ([[$voided, StoredValueRefused::class, 'is voided'], [$expired, StoredValueRefused::class, 'has expired'], [$empty, InvalidValue::class, 'A stored value moves by a positive amount.']] as [$card, $class, $message]) {
            try {
                $this->stored()->payWithGiftCard($order, $card->code->toString(), $this->at);
                self::fail('An unusable card paid.');
            } catch (InvalidValue|StoredValueRefused $refused) {
                self::assertInstanceOf($class, $refused);
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }

        self::assertSame([], $this->payments->payments);
    }

    public function testStoreCreditPaysToo(): void
    {
        new StoreCredits($this->instruments)->grant('ada', $this->cad(2_000), $this->at);
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);

        $payment = $this->stored()->payWithStoreCredit($order, 'ada', $this->at);

        self::assertSame(['store_credit', 2_000, PaymentState::Completed], [$payment->method, $payment->amount->minorUnits, $payment->state()]);
        self::assertSame(0, new StoreCredits($this->instruments)->balance('ada', Currency::of('CAD'))->minorUnits);
    }

    public function testACustomerWithoutCreditCannotPayWithIt(): void
    {
        new StoreCredits($this->instruments)->grant('ada', Money::of(2_000, Currency::of('USD')), $this->at);

        $this->expectException(StoredValueRefused::class);
        $this->expectExceptionMessageIsOrContains('ada has no store credit in CAD.');

        $this->stored()->payWithStoreCredit($this->order([['product', 'BOOK', 1, 5_000]]), 'ada', $this->at);
    }

    public function testARefundGoesBackToTheCard(): void
    {
        $issued = $this->cards()->issue($this->cad(3_000), $this->at);
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);
        $payment = $this->stored()->payWithGiftCard($order, $issued->code->toString(), $this->at);

        $this->corePayments()->refund($payment, $this->cad(1_000), 'refund-1');
        $this->corePayments()->refund($payment, $this->cad(1_000), 'refund-1');
        self::assertSame(1_000, $issued->card->balance->minorUnits);
        $this->corePayments()->refund($payment, $this->cad(2_000), 'refund-2');

        self::assertSame([3_000, PaymentState::Refunded], [$payment->refunded->minorUnits, $payment->state()]);
        self::assertSame(3_000, $issued->card->balance->minorUnits);
        $restored = array_values(array_filter($this->instruments->movements, static fn (Movement $movement): bool => MovementKind::Restored === $movement->kind));
        self::assertSame([[1_000, 'A-1', $payment->id, '2026-11-03 11:00:00'], [2_000, 'A-1', $payment->id, '2026-11-03 11:00:00']], array_map(static fn (Movement $movement): array => [$movement->amount->minorUnits, $movement->orderNumber, $movement->payment, $movement->occurredAt->format('Y-m-d H:i:s')], $restored));
    }

    /**
     * A card voided or expired since the payment takes no value back: the refund is the customer's store credit.
     */
    public function testARefundToACardThatCannotTakeItBecomesStoreCredit(): void
    {
        $expiring = $this->cards()->give($this->cad(3_000), $this->at, $this->at->modify('+30 minutes'));
        $voided = $this->cards()->issue($this->cad(1_000), $this->at);
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);
        $first = $this->stored()->payWithGiftCard($order, $expiring->code->toString(), $this->at);
        $second = $this->stored()->payWithGiftCard($order, $voided->code->toString(), $this->at);
        $this->cards()->void($voided->code->toString(), $this->at);
        $payments = $this->corePayments(new StoredValueGateway($this->instruments, new MockClock('2026-11-03 11:00:00'), $this->holders('ada')));

        $payments->refund($first, $this->cad(1_200), 'refund-1');
        $payments->refund($second, $this->cad(1_000), 'refund-2');

        self::assertSame([0, 0], [$expiring->card->balance->minorUnits, $voided->card->balance->minorUnits]);
        self::assertSame(2_200, new StoreCredits($this->instruments)->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertSame([1_200, 1_000], [$first->refunded->minorUnits, $second->refunded->minorUnits]);
        $credit = new StoreCredits($this->instruments)->of('ada', Currency::of('CAD'));
        self::assertSame([[MovementKind::Restored, 1_200, 'A-1', $first->id], [MovementKind::Restored, 1_000, 'A-1', $second->id]], array_map(static fn (Movement $movement): array => [$movement->kind, $movement->amount->minorUnits, $movement->orderNumber, $movement->payment], $this->instruments->movementsOf((string) $credit?->id)));

        new StoreCredits($this->instruments)->grant('bob', $this->cad(1), $this->at);
        $other = $this->cards()->give($this->cad(500), $this->at, $this->at->modify('+30 minutes'));
        $third = $this->stored()->payWithGiftCard($order, $other->code->toString(), $this->at);
        $this->corePayments(new StoredValueGateway($this->instruments, new MockClock('2026-11-03 11:00:00'), $this->holders('bob')))->refund($third, $this->cad(500), 'refund-3');
        self::assertSame(501, new StoreCredits($this->instruments)->balance('bob', Currency::of('CAD'))->minorUnits);
    }

    public function testARefundToAnUnusableCardWithoutAKnownCustomerIsRefused(): void
    {
        $expiring = $this->cards()->give($this->cad(3_000), $this->at, $this->at->modify('+30 minutes'));
        $payment = $this->stored()->payWithGiftCard($this->order([['product', 'BOOK', 1, 5_000]]), $expiring->code->toString(), $this->at);
        $message = \sprintf('The payment %s cannot go back to the stored value %s, voided or expired, and no customer is known to take it as store credit.', $payment->id, $expiring->card->id);

        foreach ([$this->gateway(), new StoredValueGateway($this->instruments, new MockClock('2026-11-03 11:00:00'), $this->holders(null))] as $gateway) {
            try {
                $this->corePayments($gateway)->refund($payment, $this->cad(1_000), 'refund-1');
                self::fail('The refund went nowhere.');
            } catch (StoredValueRefused $storedValueRefused) {
                self::assertSame($message, $storedValueRefused->getMessage());
            }
        }

        self::assertSame(0, $payment->refunded->minorUnits);
    }

    public function testARefundToAVoidedStoreCreditIsRefused(): void
    {
        new StoreCredits($this->instruments)->grant('ada', $this->cad(2_000), $this->at);
        $payment = $this->stored()->payWithStoreCredit($this->order([['product', 'BOOK', 1, 5_000]]), 'ada', $this->at);
        $credit = new StoreCredits($this->instruments)->of('ada', Currency::of('CAD'));
        $credit?->void($this->at);

        $this->expectException(StoredValueRefused::class);
        $this->expectExceptionMessageIsOrContains('voided or expired, and no customer is known');

        $this->corePayments(new StoredValueGateway($this->instruments, new MockClock('2026-11-03 11:00:00'), $this->holders('ada')))->refund($payment, $this->cad(1_000), 'refund-1');
    }

    public function testOnlyAStoredValuePaymentIsRefundedByTheGateway(): void
    {
        $order = $this->order([['product', 'BOOK', 1, 5_000]]);
        $payment = Payment::create($order, 'gift_card', $this->cad(1_000), $this->at);

        $this->expectException(StoredValueRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The payment %s spent no stored value.', $payment->id));

        $this->gateway()->refund($payment, $this->cad(1_000), 'refund-1');
    }

    public function testARedemptionWhoseInstrumentIsGoneIsNotRefunded(): void
    {
        $issued = $this->cards()->issue($this->cad(3_000), $this->at);
        $payment = $this->stored()->payWithGiftCard($this->order([['product', 'BOOK', 1, 5_000]]), $issued->code->toString(), $this->at);
        $this->instruments->instruments = [];

        $this->expectException(StoredValueRefused::class);

        $this->gateway()->refund($payment, $this->cad(1_000), 'refund-1');
    }

    public function testTheGatewayAnswersForItsTwoMethods(): void
    {
        $gateway = $this->gateway();

        self::assertTrue($gateway->supports('gift_card'));
        self::assertTrue($gateway->supports('store_credit'));
        self::assertFalse($gateway->supports('card'));

        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('Stored value is its own processor: it sends no notification.');

        $gateway->read('{}', []);
    }

    public function testAnUnpaidOrderIsRefusedByTheCore(): void
    {
        $issued = $this->cards()->issue($this->cad(3_000), $this->at);

        $this->expectException(PaymentRefused::class);

        $this->stored()->payWithGiftCard(Order::open('A-3', Currency::of('CAD')), $issued->code->toString(), $this->at, $this->cad(1));
    }

    private function gateway(): StoredValueGateway
    {
        return new StoredValueGateway($this->instruments, new MockClock('2026-11-03 11:00:00'));
    }

    private function corePayments(?StoredValueGateway $gateway = null): Payments
    {
        return new Payments([$gateway ?? $this->gateway()], $this->payments);
    }

    private function cards(): GiftCards
    {
        return new GiftCards($this->instruments, $this->corePayments());
    }

    private function stored(): StoredValuePayments
    {
        return new StoredValuePayments($this->corePayments(), $this->instruments, $this->cards());
    }

    private function holders(?string $holder): StoreCreditHolders
    {
        return new readonly class($holder) implements StoreCreditHolders {
            public function __construct(
                private ?string $holder,
            ) {
            }

            #[Override]
            public function holderOf(string $orderNumber): ?string
            {
                return 'A-1' === $orderNumber ? $this->holder : null;
            }
        };
    }

    /**
     * @param list<array{string, string, int, int}> $lines type, id, quantity, unit price
     */
    private function order(array $lines, string $number = 'A-1'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($lines as [$type, $id, $quantity, $price]) {
            $order->add(Sellable::of(SellableReference::of($type, $id), 'Line', $this->cad($price), 'standard'), $quantity, $this->cad($price));
        }

        $order->place($this->at);

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
