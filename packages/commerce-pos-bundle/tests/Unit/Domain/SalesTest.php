<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Layaway\LayawayState;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\ImportedSale;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterSale::class)]
#[CoversClass(SessionPayment::class)]
#[CoversClass(HeldSale::class)]
#[CoversClass(Layaway::class)]
#[CoversClass(ImportedSale::class)]
#[CoversClass(PosRefused::class)]
final class SalesTest extends TestCase
{
    public function testASaleAndAPaymentBelongToTheirSession(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $session = $this->session();

        $sale = RegisterSale::of('A-1', $session, 'ada', $at);
        $payment = SessionPayment::of('p-1', $session, 'A-1', 'debit', Money::of(1_200, Currency::of('CAD')), 'APPROVED-1', $at);

        self::assertSame(['A-1', $session->id, 'front', 'ada', $at, 0, 'CAD'], [$sale->orderNumber, $sale->session, $sale->register, $sale->by, $sale->rungAt, $sale->tip()->minorUnits, $sale->tip()->currency->code]);
        self::assertSame(250, RegisterSale::of('A-2', $session, 'ada', $at, Money::of(250, Currency::of('CAD')))->tip()->minorUnits);

        foreach ([Money::of(-1, Currency::of('CAD')), Money::of(1, Currency::of('USD'))] as $tip) {
            try {
                RegisterSale::of('A-3', $session, 'ada', $at, $tip);
                self::fail('A bad tip was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame('The tip of the sale A-3 is zero or more, in CAD.', $invalid->getMessage());
            }
        }

        self::assertSame(['p-1', $session->id, 'A-1', 'debit', 1_200, 'CAD', 'APPROVED-1', $at], [$payment->payment, $payment->session, $payment->orderNumber, $payment->method, $payment->amount->minorUnits, $payment->amount->currency->code, $payment->approval, $payment->takenAt]);
    }

    public function testAHeldSaleHasALabel(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $held = HeldSale::of('A-1', $this->register(), str_repeat('é', 64), 'ada', $at);

        self::assertSame(['A-1', 'front', str_repeat('é', 64), 'ada', $at], [$held->orderNumber, $held->register, $held->label, $held->heldBy, $held->heldAt]);

        foreach ([' ', str_repeat('é', 65)] as $label) {
            try {
                HeldSale::of('A-1', $this->register(), $label, 'ada', $at);
                self::fail('A bad label was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame('A held sale is labelled in 1 to 64 characters.', $invalid->getMessage());
            }
        }
    }

    public function testALayawayIsCompletedOrCancelledOnce(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $due = $at->modify('+30 days');
        $layaway = Layaway::of('A-1', str_repeat('é', 255), $at, $due);

        self::assertSame(['A-1', str_repeat('é', 255), $at, $due, LayawayState::Open, null], [$layaway->orderNumber, $layaway->customer, $layaway->openedAt, $layaway->dueAt, $layaway->state(), $layaway->endedAt()]);
        self::assertFalse($layaway->isOverdue($due->modify('-1 second')));
        self::assertTrue($layaway->isOverdue($due));

        $layaway->complete($due);
        self::assertSame([LayawayState::Completed, $due], [$layaway->state(), $layaway->endedAt()]);
        self::assertFalse($layaway->isOverdue($due->modify('+1 year')));

        foreach ([static fn () => $layaway->complete($due), static fn () => $layaway->cancel($due)] as $operation) {
            try {
                $operation();
                self::fail('An ended layaway ended again.');
            } catch (PosRefused $refused) {
                self::assertSame('The order A-1 is not an open layaway.', $refused->getMessage());
            }
        }

        $cancelled = Layaway::of('A-2', 'ada', $at, $due);
        $cancelled->cancel($at);
        self::assertSame([LayawayState::Cancelled, $at], [$cancelled->state(), $cancelled->endedAt()]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badLayaways(): iterable
    {
        yield 'no customer' => [' ', '+1 day', 'A layaway belongs to a customer, named in 1 to 255 characters.'];
        yield 'a long name' => [str_repeat('é', 256), '+1 day', 'A layaway belongs to a customer, named in 1 to 255 characters.'];
        yield 'due at once' => ['ada', '+0 seconds', 'A layaway is due after it is opened.'];
    }

    #[DataProvider('badLayaways')]
    public function testALayawayHasACustomerAndADueDate(string $customer, string $due, string $message): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Layaway::of('A-1', $customer, $at, $at->modify($due));
    }

    public function testAnImportedSaleKeepsWhatRepricingFound(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $session = $this->session();
        $imported = ImportedSale::of('sale-1', $session, Money::of(-25, Currency::of('CAD')), $at, $at->modify('+1 hour'));

        self::assertSame(['sale-1', $session->id, -25, 'CAD', $at, '2026-11-03 11:00:00', false], [$imported->id, $imported->session, $imported->discrepancy->minorUnits, $imported->discrepancy->currency->code, $imported->rungAt, $imported->importedAt->format('Y-m-d H:i:s'), $imported->agrees()]);
        self::assertTrue(ImportedSale::of('sale-2', $session, Money::of(0, Currency::of('CAD')), $at, $at)->agrees());
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame([
            'No active register is known as "front".',
            'The register front already has an open session.',
            'The register front has no open session.',
            '5.00 tendered is less than the 5.05 due in cash.',
            'The order A-1 is not a cart: only a cart is held, tipped or rung up.',
            'No sale is held as A-1.',
            'The layaway A-1 needs a deposit of 10.00; 5.00 is paid.',
            '"cheque" is not a method the register records.',
            'The payment p-1 was not taken in cash at a register.',
        ], [
            PosRefused::unknownRegister('front')->getMessage(),
            PosRefused::sessionAlreadyOpen('front')->getMessage(),
            PosRefused::noOpenSession('front')->getMessage(),
            PosRefused::notEnoughTendered('5.00', '5.05')->getMessage(),
            PosRefused::notACart('A-1')->getMessage(),
            PosRefused::notHeld('A-1')->getMessage(),
            PosRefused::depositShort('A-1', '5.00', '10.00')->getMessage(),
            PosRefused::methodNotRecorded('cheque')->getMessage(),
            PosRefused::notTakenHere('p-1')->getMessage(),
        ]);
        self::assertInstanceOf(DomainError::class, PosRefused::notHeld('A-1'));
    }

    private function session(): CashSession
    {
        return CashSession::open($this->register(), 'ada', Money::of(0, Currency::of('CAD')), new DateTimeImmutable())[0];
    }

    private function register(): Register
    {
        return Register::of('front', 'Front', Currency::of('CAD'), 5);
    }
}
