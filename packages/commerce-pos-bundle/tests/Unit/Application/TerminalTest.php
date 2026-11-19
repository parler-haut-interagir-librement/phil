<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\NotificationOutcome;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommercePosBundle\Application\Contract\PaymentTerminal;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Application\TerminalAdapters;
use Phil\CommercePosBundle\Application\TerminalCharge;
use Phil\CommercePosBundle\Application\TerminalGateway;
use Phil\CommercePosBundle\Application\TerminalPayments;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Tests\Unit\Support\Till;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TerminalPayments::class)]
#[CoversClass(TerminalGateway::class)]
#[CoversClass(TerminalAdapters::class)]
#[CoversClass(TerminalCharge::class)]
#[CoversClass(Registers::class)]
final class TerminalTest extends TestCase
{
    private Till $till;

    private DateTimeImmutable $at;

    private CashSession $session;

    #[Override]
    protected function setUp(): void
    {
        $this->till = new Till();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->till->registers->addTerminal('t1', 'front', 'fake', 'tmr_FRONT', 'Counter reader');
        $this->session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
    }

    public function testAChargeIsPresentedOnTheDeviceAndCountedOnceCaptured(): void
    {
        $order = $this->placed();
        $payment = $this->till->terminalPayments->charge($order, $this->session, 't1', $this->at);

        self::assertSame(['card_present', 499, PaymentState::Processing, $payment->id], [$payment->method, $payment->amount->minorUnits, $payment->state(), $payment->externalReference()]);
        $charge = $this->till->terminal->presented()[0];
        self::assertSame([$payment->id, 'A-1', 'tmr_FRONT', 499], [$charge->payment, $charge->orderNumber, $charge->device, $charge->amount->minorUnits]);
        $presented = $this->till->pos->terminalPayments[$payment->id];
        self::assertSame(['t1', $this->session->id, 'fake_1_'.$payment->id, false], [$presented->terminal, $presented->session, $presented->processorReference, $presented->isCounted()]);
        self::assertSame([], $this->till->sessions->report($this->session->id)->byMethod);

        [$payload, $headers] = $this->till->terminal->notification('evt-1', $payment->id, PaymentEvent::Captured);
        [$outcome, $notified] = $this->till->terminalPayments->notify($payload, $headers, $this->at);

        self::assertSame([NotificationOutcome::Applied, $payment, PaymentState::Completed], [$outcome, $notified, $payment->state()]);
        self::assertSame(['card_present' => 499], array_map(static fn (Money $amount): int => $amount->minorUnits, $this->till->sessions->report($this->session->id)->byMethod));
        self::assertSame('fake_1_'.$payment->id, $this->till->pos->payments[$payment->id]->approval);

        [$again] = $this->till->terminalPayments->notify($payload, $headers, $this->at);
        [$payload, $headers] = $this->till->terminal->notification('evt-2', $payment->id, PaymentEvent::Captured);
        $this->till->terminalPayments->notify($payload, $headers, $this->at);

        self::assertSame(NotificationOutcome::AlreadyApplied, $again);
        self::assertCount(1, $this->till->pos->payments);
        self::assertTrue($this->till->payments->summary($order)->payable()->isZero());
    }

    public function testAFailedChargeIsNotCounted(): void
    {
        $payment = $this->till->terminalPayments->charge($this->placed(), $this->session, 't1', $this->at, $this->cad(200));
        [$payload, $headers] = $this->till->terminal->notification('evt-1', $payment->id, PaymentEvent::Failed);

        [$outcome] = $this->till->terminalPayments->notify($payload, $headers, $this->at);

        self::assertSame([NotificationOutcome::Applied, 200, PaymentState::Failed], [$outcome, $payment->amount->minorUnits, $payment->state()]);
        self::assertSame([], $this->till->pos->payments);
        self::assertFalse($this->till->pos->terminalPayments[$payment->id]->isCounted());
    }

    public function testANotificationForAPaymentNobodyPresentedIsAppliedButNotCounted(): void
    {
        [$payload, $headers] = $this->till->terminal->notification('evt-1', 'unknown', PaymentEvent::Captured);

        self::assertSame([NotificationOutcome::UnknownPayment, null], $this->till->terminalPayments->notify($payload, $headers, $this->at));
    }

    public function testACaptureWhoseSessionIsGoneIsAppliedButNotCounted(): void
    {
        $payment = $this->till->terminalPayments->charge($this->placed(), $this->session, 't1', $this->at);
        unset($this->till->pos->sessions[$this->session->id]);
        [$payload, $headers] = $this->till->terminal->notification('evt-1', $payment->id, PaymentEvent::Captured);

        [$outcome] = $this->till->terminalPayments->notify($payload, $headers, $this->at);

        self::assertSame([NotificationOutcome::Applied, PaymentState::Completed], [$outcome, $payment->state()]);
        self::assertSame([], $this->till->pos->payments);
        self::assertFalse($this->till->pos->terminalPayments[$payment->id]->isCounted());
    }

    public function testAForgedNotificationIsRefused(): void
    {
        [$payload] = $this->till->terminal->notification('evt-1', 'p', PaymentEvent::Captured);

        try {
            $this->till->terminalPayments->notify($payload, ['x-fake-terminal-signature' => ['forged']], $this->at);
            self::fail('A forged notification was read.');
        } catch (InvalidNotification $invalidNotification) {
            self::assertSame("The fake terminal's signature does not verify.", $invalidNotification->getMessage());
        }

        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('No payment terminal adapter recognises the notification.');

        $this->till->terminalPayments->notify($payload, ['stripe-signature' => ['t=1']], $this->at);
    }

    public function testAChargeIsCancelledOnTheDevice(): void
    {
        $order = $this->placed();
        $payment = $this->till->terminalPayments->charge($order, $this->session, 't1', $this->at);

        $this->till->terminalPayments->cancel($payment);

        self::assertSame(['fake_1_'.$payment->id], $this->till->terminal->cancelled());
        self::assertSame(PaymentState::Abandoned, $payment->state());
        self::assertSame(499, $this->till->payments->summary($order)->payable()->minorUnits);
    }

    public function testAPaymentNeverPresentedIsNotCancelledNorRefundedThroughATerminal(): void
    {
        $order = $this->placed();
        [$payment] = $this->till->payments->start($order, 'card_present', $this->at);

        try {
            $this->till->terminalPayments->cancel($payment);
            self::fail('A payment never presented was cancelled.');
        } catch (PosRefused $posRefused) {
            self::assertSame(\sprintf('The payment %s was not presented on a terminal.', $payment->id), $posRefused->getMessage());
        }

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('was not presented on a terminal.');

        $this->till->terminalGateway->refund($payment, $this->cad(100), 'refund-1');
    }

    public function testARefundOnATerminalSinceRemovedIsRefused(): void
    {
        $payment = $this->till->terminalPayments->charge($this->placed(), $this->session, 't1', $this->at);
        [$payload, $headers] = $this->till->terminal->notification('evt-1', $payment->id, PaymentEvent::Captured);
        $this->till->terminalPayments->notify($payload, $headers, $this->at);
        $this->till->pos->terminals = [];

        try {
            $this->till->payments->refund($payment, $this->cad(150), 'refund-1');
            self::fail('A refund went to a terminal that is gone.');
        } catch (PosRefused $posRefused) {
            self::assertStringContainsString('t1', $posRefused->getMessage());
        }

        self::assertSame([], $this->till->terminal->refunded());
    }

    public function testARefundIsAskedOfTheProcessorAndRecordedOnItsNotification(): void
    {
        $payment = $this->till->terminalPayments->charge($this->placed(), $this->session, 't1', $this->at);
        [$payload, $headers] = $this->till->terminal->notification('evt-1', $payment->id, PaymentEvent::Captured);
        $this->till->terminalPayments->notify($payload, $headers, $this->at);

        $this->till->payments->refund($payment, $this->cad(150), 'refund-1');

        self::assertSame([['fake_1_'.$payment->id, 150, 'refund-1']], array_map(static fn (array $refund): array => [$refund[0], $refund[1]->minorUnits, $refund[2]], $this->till->terminal->refunded()));
        self::assertSame(0, $payment->refunded->minorUnits);

        [$payload, $headers] = $this->till->terminal->notification('evt-2', $payment->id, PaymentEvent::Refunded, $this->cad(150));
        $this->till->terminalPayments->notify($payload, $headers, $this->at);

        self::assertSame(150, $payment->refunded->minorUnits);
        self::assertCount(1, $this->till->pos->payments);
    }

    public function testADeviceThatFailsGivesThePaymentBack(): void
    {
        $adapter = new class implements PaymentTerminal {
            #[Override]
            public function name(): string
            {
                return 'broken';
            }

            #[Override]
            public function present(TerminalCharge $charge): string
            {
                throw new RuntimeException('The reader is offline.');
            }

            #[Override]
            public function cancel(string $device, string $processorReference): void
            {
            }

            #[Override]
            public function refund(string $processorReference, Money $amount, string $idempotencyKey): void
            {
            }

            #[Override]
            public function recognises(array $headers): bool
            {
                return false;
            }

            #[Override]
            public function read(string $payload, array $headers): PaymentNotification
            {
                throw InvalidNotification::because('Never.');
            }
        };
        $adapters = new TerminalAdapters([$this->till->terminal, $adapter]);
        $gateway = new TerminalGateway($adapters, $this->till->pos, $this->till->pos);
        $payments = new TerminalPayments($this->till->payments, $gateway, $adapters, $this->till->registers, $this->till->pos, $this->till->pos);
        $this->till->registers->addTerminal('t2', 'front', 'broken', 'tmr_2', 'Back reader');
        $order = $this->placed();

        try {
            $payments->charge($order, $this->session, 't2', $this->at);
            self::fail('A charge the device never showed was kept.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The reader is offline.', $runtimeException->getMessage());
        }

        $abandoned = array_first($this->till->paymentStore->payments);
        self::assertInstanceOf(Payment::class, $abandoned);
        self::assertSame(PaymentState::Abandoned, $abandoned->state());
        self::assertSame(499, $this->till->payments->summary($order)->payable()->minorUnits);
        self::assertSame([], $this->till->pos->terminalPayments);
    }

    public function testAChargeNeedsAnOpenSessionAndATerminalOfItsRegister(): void
    {
        $order = $this->placed();
        $this->till->registers->create('cart', 'Market cart', Currency::of('CAD'), 5, 'store-1');
        $other = $this->till->sessions->open('cart', 'bob', $this->cad(0), $this->at);

        foreach ([
            [$other, 't1', 'The terminal t1 is not at the register cart.'],
            [$this->session, 'nope', 'No active terminal is known as "nope".'],
        ] as [$session, $terminal, $message]) {
            try {
                $this->till->terminalPayments->charge($order, $session, $terminal, $this->at);
                self::fail($message);
            } catch (PosRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        $this->till->registers->terminal('t1')->retire();

        try {
            $this->till->terminalPayments->charge($order, $this->session, 't1', $this->at);
            self::fail('A retired terminal took a charge.');
        } catch (PosRefused $posRefused) {
            self::assertSame('No active terminal is known as "t1".', $posRefused->getMessage());
        }

        $this->till->pos->terminals['t1']->reinstate();
        $this->till->sessions->close($this->session->id, $this->cad(0), 'ada', $this->at);

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The session %s is closed.', $this->session->id));

        $this->till->terminalPayments->charge($order, $this->session, 't1', $this->at);
    }

    public function testATerminalNamesAnAdapterTheApplicationRegistered(): void
    {
        $this->till->registers->addTerminal('t9', 'front', 'other', 'tmr_9', 'Spare');

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('No payment terminal adapter is named "other".');

        $this->till->terminalPayments->charge($this->placed(), $this->session, 't9', $this->at);
    }

    public function testOnlyTheMainRegisterHasPinTerminals(): void
    {
        $this->till->registers->create('phone-1', "Ada's phone", Currency::of('CAD'), 5, 'store-1');

        try {
            $this->till->registers->addTerminal('t2', 'phone-1', 'fake', 'tmr_2', 'Tap reader');
            self::fail('A mobile register took a PIN terminal.');
        } catch (PosRefused $posRefused) {
            self::assertSame('The terminal t2 takes PIN payments: only a main register has one, and phone-1 is mobile.', $posRefused->getMessage());
        }

        $tap = $this->till->registers->addTerminal('t2', 'phone-1', 'fake', 'tmr_2', 'Tap reader', pinEntry: false);
        self::assertSame(['phone-1', 'fake', 'tmr_2', 'Tap reader', false, true], [$tap->register, $tap->adapter, $tap->device, $tap->label(), $tap->pinEntry, $tap->isActive()]);
        self::assertSame([$tap], $this->till->registers->terminalsAt('phone-1'));

        try {
            $this->till->registers->assign('front', RegisterRole::Mobile);
            self::fail('A main register with a PIN terminal became mobile.');
        } catch (PosRefused $posRefused) {
            self::assertSame('The terminal t1 takes PIN payments: only a main register has one, and front is mobile.', $posRefused->getMessage());
        }

        self::assertTrue($this->till->registers->get('front')->isMain());
    }

    public function testOneMainRegisterPerLocation(): void
    {
        $this->till->registers->create('back', 'Back', Currency::of('CAD'), 5, 'store-1');

        foreach ([
            fn (): Register => $this->till->registers->create('tablet', 'Tablet', Currency::of('CAD'), 5, 'store-1', role: RegisterRole::Main),
            fn (): Register => $this->till->registers->assign('back', RegisterRole::Main),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('A location took two main registers.');
            } catch (PosRefused $refused) {
                self::assertStringStartsWith('The location store-1 already has a main register:', $refused->getMessage());
            }
        }

        self::assertSame('front', $this->till->registers->mainAt('store-1')?->code);
        self::assertSame('front', $this->till->registers->assign('front', RegisterRole::Main)->code);
        self::assertTrue($this->till->registers->create('kiosk', 'Kiosk', Currency::of('CAD'), role: RegisterRole::Main)->isMain());
        self::assertNull($this->till->registers->mainAt('store-2'));

        $this->till->pos->terminals = [];
        $this->till->registers->assign('front', RegisterRole::Mobile);
        self::assertSame(RegisterRole::Main, $this->till->registers->assign('back', RegisterRole::Main)->role());

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('The location (none) already has a main register: other cannot be one too.');

        $this->till->registers->create('other', 'Other', Currency::of('CAD'), role: RegisterRole::Main);
    }

    public function testATerminalIsChecked(): void
    {
        foreach ([
            ['T1', 'fake', 'dev', 'Label', '"T1" is not a terminal code'],
            ['t1b', 'Fake', 'dev', 'Label', '"Fake" is not an adapter name'],
            ['t1c', 'fake', 'a b', 'Label', "A terminal's device is named in 1 to 255 visible ASCII characters."],
            ['t1d', 'fake', 'dev', ' ', 'A terminal is labelled in 1 to 64 characters.'],
            ['t1e', 'fake', 'dev', str_repeat('é', 65), 'A terminal is labelled in 1 to 64 characters.'],
        ] as [$code, $adapter, $device, $label, $message]) {
            try {
                $this->till->registers->addTerminal($code, 'front', $adapter, $device, $label);
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertStringStartsWith($message, $invalid->getMessage());
            }
        }

        $terminal = $this->till->registers->terminal('t1');
        $terminal->relabel(str_repeat('é', 64));
        self::assertSame(str_repeat('é', 64), $terminal->label());
    }

    private function placed(): Order
    {
        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->ring($order, $this->session, 'ada', $this->at);

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
