<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\CashTender;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Application\RegisterGateway;
use Phil\CommercePosBundle\Application\RegisterPayments;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Application\SessionReport;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\CommercePosBundle\Tests\Unit\Support\Till;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Registers::class)]
#[CoversClass(CashSessions::class)]
#[CoversClass(SessionReport::class)]
#[CoversClass(RegisterCheckout::class)]
#[CoversClass(RegisterPayments::class)]
#[CoversClass(CashTender::class)]
#[CoversClass(RegisterGateway::class)]
final class RegisterTest extends TestCase
{
    private Till $till;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->till = new Till();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testRegistersAreCreatedAndRetired(): void
    {
        $register = $this->till->registers->create('back', 'Back', Currency::of('USD'));
        self::assertSame([1, null, null], [$register->rounding()->increment, $register->location(), $register->channel()]);

        self::assertSame($register, $this->till->registers->get('back'));
        self::assertSame(['store-1', 5], [$this->till->registers->get('front')->location(), $this->till->registers->get('front')->rounding()->increment]);

        $register->retire();

        foreach (['back', 'nowhere'] as $code) {
            try {
                $this->till->registers->get($code);
                self::fail('A missing register was found.');
            } catch (PosRefused $refused) {
                self::assertSame(\sprintf('No active register is known as "%s".', $code), $refused->getMessage());
            }
        }
    }

    public function testOneSessionIsOpenPerRegister(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(10_000), $this->at);

        self::assertSame($session, $this->till->sessions->current('front'));
        self::assertSame($session, $this->till->sessions->get($session->id));
        self::assertSame([CashMovementKind::Float], array_map(static fn (CashMovement $movement): CashMovementKind => $movement->kind, $this->till->pos->movements));

        try {
            $this->till->sessions->open('front', 'bob', $this->cad(0), $this->at);
            self::fail('A second session opened.');
        } catch (PosRefused $posRefused) {
            self::assertSame('The register front already has an open session.', $posRefused->getMessage());
        }

        $this->till->sessions->close($session->id, $this->cad(10_000), 'ada', $this->at);
        self::assertTrue($this->till->sessions->open('front', 'bob', $this->cad(0), $this->at)->isOpen());
    }

    public function testARegisterWithoutSessionIsSaidSo(): void
    {
        try {
            $this->till->sessions->current('front');
            self::fail('A session was found.');
        } catch (PosRefused $posRefused) {
            self::assertSame('The register front has no open session.', $posRefused->getMessage());
        }

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('The session nope is closed.');

        $this->till->sessions->get('nope');
    }

    public function testCashIsTakenRoundedAndTheChangeGiven(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(10_000), $this->at);
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);

        $tender = $this->till->registerPayments->cash($order, $session, $this->cad(5_000), 'ada', $this->at);

        self::assertSame([2_198, 2_200, 5_000, 2_800], [$tender->payment->amount->minorUnits, $tender->due->minorUnits, $tender->tendered->minorUnits, $tender->change->minorUnits]);
        self::assertSame(['cash', PaymentState::Completed], [$tender->payment->method, $tender->payment->state()]);
        $movement = $this->till->pos->cashMovementOf($tender->payment->id);
        self::assertSame([CashMovementKind::Sale, 2_200, 2, 'ada'], [$movement?->kind, $movement?->amount->minorUnits, $movement?->rounding->minorUnits, $movement?->by]);
        self::assertSame(12_200, $session->expected->minorUnits);
        $recorded = $this->till->pos->findPayment($tender->payment->id);
        self::assertSame([$session->id, 'A-1', 'cash', 2_198, null], [$recorded?->session, $recorded?->orderNumber, $recorded?->method, $recorded?->amount->minorUnits, $recorded?->approval]);
        self::assertTrue($this->till->payments->summary($order)->payable()->isZero());
    }

    public function testExactCashNeedsNoChange(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);

        self::assertSame(0, $this->till->registerPayments->cash($order, $session, $this->cad(2_200), 'ada', $this->at)->change->minorUnits);
    }

    public function testTooLittleCashIsRefusedBeforeAnyPayment(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);

        try {
            $this->till->registerPayments->cash($order, $session, $this->cad(2_199), 'ada', $this->at);
            self::fail('Too little cash was taken.');
        } catch (PosRefused $posRefused) {
            self::assertSame('21.99 tendered is less than the 22.00 due in cash.', $posRefused->getMessage());
        }

        self::assertSame([], $this->till->paymentStore->payments);
    }

    public function testASplitPaymentTakesCashAndACard(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);

        $card = $this->till->registerPayments->recorded($order, $session, 'debit', $this->cad(1_000), 'APPROVED-7', $this->at);
        $cash = $this->till->registerPayments->cash($order, $session, $this->cad(1_200), 'ada', $this->at, $this->cad(1_198));

        self::assertSame(['debit', PaymentState::Completed, $card->id], [$card->method, $card->state(), $card->externalReference()]);
        self::assertSame('APPROVED-7', $this->till->pos->findPayment($card->id)?->approval);
        self::assertSame([1_200, 0], [$cash->due->minorUnits, $cash->change->minorUnits]);
    }

    public function testPartOfWhatIsLeftMayBePaidInCash(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);

        $cash = $this->till->registerPayments->cash($order, $session, $this->cad(1_000), 'ada', $this->at, $this->cad(1_000));

        self::assertSame([1_000, 1_000, 0], [$cash->payment->amount->minorUnits, $cash->due->minorUnits, $cash->change->minorUnits]);
        self::assertSame(1_198, $this->till->payments->summary($order)->payable()->minorUnits);
    }

    public function testOnlyConfiguredMethodsAreRecorded(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('"cheque" is not a method the register records.');

        $this->till->registerPayments->recorded($order, $session, 'cheque', $this->cad(499), null, $this->at);
    }

    public function testAnotherModulesPaymentIsCounted(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        $payment = Payment::create($order, 'gift_card', $this->cad(499), $this->at);

        $this->till->registerPayments->count($payment, $session, $this->at, 'W8HC');

        $counted = $this->till->pos->findPayment($payment->id);
        self::assertSame(['A-1', 'gift_card', 499, 'W8HC', $this->at], [$counted?->orderNumber, $counted?->method, $counted?->amount->minorUnits, $counted?->approval, $counted?->takenAt]);
    }

    public function testCashIsRefundedOutOfTheDrawer(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(10_000), $this->at);
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        $tender = $this->till->registerPayments->cash($order, $session, $this->cad(2_200), 'ada', $this->at);

        $given = $this->till->registerPayments->refundCash($tender->payment, $this->cad(499), $session, 'bob', $this->at, 'refund-1');

        self::assertSame(500, $given->minorUnits);
        self::assertSame([499, PaymentState::Completed], [$tender->payment->refunded->minorUnits, $tender->payment->state()]);
        $refund = $this->till->pos->movements[2];
        self::assertSame([CashMovementKind::Refund, -500, -1, 'bob', $tender->payment->id], [$refund->kind, $refund->amount->minorUnits, $refund->rounding->minorUnits, $refund->by, $refund->payment]);
        self::assertSame(11_700, $session->expected->minorUnits);
    }

    public function testACashRefundAskedAgainUnderItsKeyGivesNothingMore(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(10_000), $this->at);
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        $tender = $this->till->registerPayments->cash($order, $session, $this->cad(2_200), 'ada', $this->at);
        $this->till->registerPayments->refundCash($tender->payment, $this->cad(499), $session, 'bob', $this->at, 'refund-1');

        $again = $this->till->registerPayments->refundCash($tender->payment, $this->cad(499), $session, 'bob', $this->at, 'refund-1');
        $other = $this->till->registerPayments->refundCash($tender->payment, $this->cad(100), $session, 'bob', $this->at, 'refund-2');

        self::assertSame([0, 'CAD', 100], [$again->minorUnits, $again->currency->code, $other->minorUnits]);
        self::assertSame(599, $tender->payment->refunded->minorUnits);
        self::assertCount(4, $this->till->pos->movements);
        self::assertSame(11_600, $session->expected->minorUnits);
    }

    public function testOnlyCashIsRefundedInCash(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        $card = $this->till->registerPayments->recorded($order, $session, 'credit', $this->cad(499), null, $this->at);

        try {
            $this->till->registerPayments->refundCash($card, $this->cad(100), $session, 'ada', $this->at, 'refund-1');
            self::fail('A card was refunded in cash.');
        } catch (PosRefused $posRefused) {
            self::assertSame(\sprintf('The payment %s was not taken in cash at a register.', $card->id), $posRefused->getMessage());
        }

        $this->till->registerPayments->refundRecorded($card, $this->cad(100), 'refund-1');
        $this->till->registerPayments->refundRecorded($card, $this->cad(100), 'refund-1');
        self::assertSame(100, $card->refunded->minorUnits);
    }

    public function testOnlyARecordedPaymentIsRefundedAsRecorded(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        $cash = $this->till->registerPayments->cash($order, $session, $this->cad(500), 'ada', $this->at);

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('"cash" is not a method the register records.');

        $this->till->registerPayments->refundRecorded($cash->payment, $this->cad(100), 'refund-1');
    }

    public function testAClosedSessionTakesNoPayment(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        $cash = $this->till->registerPayments->cash($order, $session, $this->cad(500), 'ada', $this->at);
        $session->close($this->cad(500), 'ada', $this->at);
        $message = \sprintf('The session %s is closed.', $session->id);

        foreach ([
            fn (): CashTender => $this->till->registerPayments->cash($order, $session, $this->cad(500), 'ada', $this->at),
            fn (): Payment => $this->till->registerPayments->recorded($order, $session, 'debit', $this->cad(1), null, $this->at),
            fn () => $this->till->registerPayments->count($cash->payment, $session, $this->at),
            fn (): Money => $this->till->registerPayments->refundCash($cash->payment, $this->cad(1), $session, 'ada', $this->at, 'refund-1'),
            fn (): RegisterSale => $this->till->checkout->ring($this->till->cart(['TEA' => 1], 'A-2'), $session, 'ada', $this->at),
        ] as $operation) {
            try {
                $operation();
                self::fail('A closed session took a payment.');
            } catch (PosRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        self::assertSame(0, $cash->payment->refunded->minorUnits);
    }

    public function testASessionOfARemovedRegisterTakesNoCash(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        $this->till->pos->registers = [];

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('No active register is known as "front".');

        $this->till->registerPayments->cash($order, $session, $this->cad(500), 'ada', $this->at);
    }

    public function testACartIsHeldAndPickedUpAgain(): void
    {
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->cart(['MUG' => 1], 'A-2');

        $held = $this->till->checkout->hold($order, 'front', 'Mme Tremblay', 'ada', $this->at);
        $this->till->checkout->hold($this->till->orders->orders['A-2'], 'front', 'Red coat', 'ada', $this->at);

        self::assertSame(['A-1', 'front', 'Mme Tremblay'], [$held->orderNumber, $held->register, $held->label]);
        self::assertSame(['Mme Tremblay', 'Red coat'], array_map(static fn (HeldSale $sale): string => $sale->label, $this->till->checkout->held('front')));
        self::assertSame($order, $this->till->checkout->resume('A-1', 'front'));
        self::assertSame(['Red coat'], array_map(static fn (HeldSale $sale): string => $sale->label, $this->till->checkout->held('front')));

        try {
            $this->till->checkout->resume('A-1', 'front');
            self::fail('A sale was resumed twice.');
        } catch (PosRefused $posRefused) {
            self::assertSame('No sale is held as A-1.', $posRefused->getMessage());
        }
    }

    public function testAHeldSaleWhoseOrderIsGoneIsNotResumed(): void
    {
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->hold($order, 'front', 'Ada', 'ada', $this->at);
        $this->till->orders->orders = [];

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('No sale is held as A-1.');

        $this->till->checkout->resume('A-1', 'front');
    }

    public function testOnlyACartIsHeldTippedOrRungUp(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $session, 'ada', $this->at);

        foreach ([
            fn (): HeldSale => $this->till->checkout->hold($order, 'front', 'Ada', 'ada', $this->at),
            fn () => $this->till->checkout->tip($order, $this->cad(100)),
            fn (): RegisterSale => $this->till->checkout->ring($order, $session, 'ada', $this->at),
        ] as $operation) {
            try {
                $operation();
                self::fail('A placed order was changed.');
            } catch (PosRefused $refused) {
                self::assertSame('The order A-1 is not a cart: only a cart is held, tipped or rung up.', $refused->getMessage());
            }
        }
    }

    public function testATipIsAddedReplacedAndRemoved(): void
    {
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);

        $this->till->checkout->tip($order, $this->cad(300));
        $this->till->checkout->tip($order, $this->cad(400));

        $tips = $order->adjustments(AdjustmentType::Tip);
        self::assertCount(1, $tips);
        self::assertSame(['Pourboire', 400, null], [$tips[0]->label, $tips[0]->amount->minorUnits, $tips[0]->line]);
        self::assertSame(2_598, $order->total()->minorUnits);

        $this->till->checkout->tip($order, $this->cad(0));
        self::assertSame([], $order->adjustments(AdjustmentType::Tip));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A tip is zero or more.');

        $this->till->checkout->tip($order, $this->cad(-1));
    }

    public function testATipIsAShareOfTheItems(): void
    {
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);

        self::assertSame(330, $this->till->checkout->tipShare($order, 150_000)->minorUnits);
        self::assertSame(330, $order->adjustmentsTotal(AdjustmentType::Tip)->minorUnits);
        self::assertSame(2_198, $this->till->checkout->tipShare($order, 1_000_000)->minorUnits);
        self::assertSame(0, $this->till->checkout->tipShare($order, 0)->minorUnits);
        self::assertSame(1, $this->till->checkout->tipShare($order, 228)->minorUnits);
        self::assertSame(0, $this->till->checkout->tipShare($order, 227)->minorUnits);

        foreach ([-1, 1_000_001] as $share) {
            try {
                $this->till->checkout->tipShare($order, $share);
                self::fail('A bad share was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame(\sprintf('A tip is 0 to 1000000 parts per million of the items; %d is refused.', $share), $invalid->getMessage());
            }
        }
    }

    public function testRingingUpKeepsTheTipAndRecordsTheSale(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->till->cart(['TEA' => 2]);
        $this->till->checkout->tip($order, $this->cad(200));

        $sale = $this->till->checkout->ring($order, $session, 'bob', $this->at);

        self::assertSame([OrderState::Placed, 1_198], [$order->state(), $order->total()->minorUnits]);
        self::assertSame(['A-1', $session->id, 'front', 'bob', $this->at, 200], [$sale->orderNumber, $sale->session, $sale->register, $sale->by, $sale->rungAt, $sale->tip()->minorUnits]);
        self::assertSame([$sale], $this->till->pos->salesOf($session->id));
    }

    public function testTheEndOfDayReport(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(10_000), $this->at);
        $first = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $this->till->checkout->tip($first, $this->cad(300));
        $this->till->checkout->ring($first, $session, 'ada', $this->at);

        $cash = $this->till->registerPayments->cash($first, $session, $this->cad(3_000), 'ada', $this->at);
        $second = $this->till->cart(['MUG' => 1], 'A-2');
        $this->till->checkout->ring($second, $session, 'ada', $this->at);
        $this->till->registerPayments->recorded($second, $session, 'debit', $this->cad(400), 'X', $this->at);
        $this->till->registerPayments->recorded($second, $session, 'debit', $this->cad(400), 'Y', $this->at);
        $this->till->registerPayments->recorded($second, $session, 'credit', $this->cad(400), 'Z', $this->at);
        $this->till->registerPayments->refundCash($cash->payment, $this->cad(103), $session, 'ada', $this->at, 'refund-1');
        $this->till->sessions->paidIn($session->id, $this->cad(2_000), 'ada', $this->at, 'Change from the safe');
        $this->till->sessions->paidOut($session->id, $this->cad(1_500), 'ada', $this->at, 'Milk');
        $this->till->sessions->drop($session->id, $this->cad(5_000), 'ada', $this->at);
        // The report reads the tips from the sales, not from the orders: none is looked up.
        $tipped = $this->till->cart(['TEA' => 1], 'A-3');
        $this->till->checkout->tip($tipped, $this->cad(50));
        $this->till->checkout->ring($tipped, $session, 'ada', $this->at);
        $this->till->orders->orders = [];

        $report = $this->till->sessions->close($session->id, $this->cad(8_000), 'bob', $this->at);

        self::assertSame([10_000, 2_500, 105, 2_000, 1_500, 5_000], array_map(static fn (Money $money): int => $money->minorUnits, [$report->float, $report->cashSales, $report->cashRefunds, $report->paidIn, $report->paidOut, $report->drops]));
        self::assertSame(0, $report->rounding->minorUnits);
        self::assertSame(['cash' => 2_498, 'credit' => 400, 'debit' => 800], array_map(static fn (Money $money): int => $money->minorUnits, $report->byMethod));
        self::assertSame([350, 3], [$report->tips->minorUnits, $report->sales]);
        self::assertSame([7_895, 8_000, 105], [$report->expected()->minorUnits, $report->counted()?->minorUnits, $report->overShort()?->minorUnits]);
        self::assertSame($session, $report->session);
        self::assertSame('Milk', $this->till->pos->movements[4]->note);
    }

    public function testAnOpenSessionIsReportedSoFar(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(10_000), $this->at);

        $report = $this->till->sessions->report($session->id);

        self::assertSame([10_000, 0, [], 0, 0, null, null], [$report->float->minorUnits, $report->cashSales->minorUnits, $report->byMethod, $report->tips->minorUnits, $report->sales, $report->counted(), $report->overShort()]);
    }

    public function testTheRegisterGatewayIsItsOwnProcessor(): void
    {
        self::assertTrue($this->till->gateway->supports('cash'));
        self::assertTrue($this->till->gateway->supports('debit'));
        self::assertFalse($this->till->gateway->supports('gift_card'));
        self::assertTrue($this->till->gateway->records('credit'));
        self::assertFalse($this->till->gateway->records('cash'));

        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('The register is its own processor: it sends no notification.');

        $this->till->gateway->read('{}', []);
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
