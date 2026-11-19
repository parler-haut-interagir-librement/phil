<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * The module's storage on a database: the tables created from the mapping, what is kept read back, each query's
 * filters and order, and moments kept in UTC whatever the zone they were given in.
 */
abstract class RepositoryTestCase extends TestCase
{
    private const string PAY_1 = '0192f3c4-0000-7000-8000-000000000001';

    private const string PAY_2 = '0192f3c4-0000-7000-8000-000000000002';

    private Server $server;

    /**
     * The URL of the database, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function setUp(): void
    {
        $url = $this->url();

        if (null === $url) {
            self::markTestSkipped('Set the URL of the server to run this test against it.');
        }

        $this->server = Server::connect($url);
        $this->server->recreate();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->server)) {
            $this->server->entityManager->getConnection()->close();
        }
    }

    public function testRegistersTerminalsPresetsAndPinsAreKept(): void
    {
        $repository = $this->server->repository();
        $front = Register::of('front', 'Front', $this->cad(), 5, 'store-1', 'counter', RegisterRole::Main);
        $repository->addRegister($front);
        $repository->addRegister(Register::of('phone-1', 'Phone', $this->cad(), 5, 'store-1'));
        $repository->addRegister(Register::of('kiosk', 'Kiosk', $this->cad(), 1, null, null, RegisterRole::Main));

        $retired = Register::of('old', 'Old', $this->cad(), 1, 'store-2', null, RegisterRole::Main);
        $retired->retire();

        $repository->addRegister($retired);
        $repository->addTerminal(Terminal::of('t2', $front, 'fake', 'tmr_2', 'Two', true));
        $repository->addTerminal(Terminal::of('t1', $front, 'fake', 'tmr_1', 'One', false));

        $gone = Terminal::of('t0', $front, 'fake', 'tmr_0', 'Gone', true);
        $gone->retire();

        $repository->addTerminal($gone);
        $repository->addPreset(SalePreset::of('wholesale', 'Wholesale', 'b2b', 'resellers', true));
        $repository->addPreset(SalePreset::of('con', 'Convention', null));

        $off = SalePreset::of('aaa', 'Off', null);
        $off->retire();

        $repository->addPreset($off);
        $repository->addCashierPin(CashierPin::of('ada', '$2y$04$hash', $this->at('2026-11-03 10:00:00')));
        $repository->addCashierPin(CashierPin::of('bob', '$2y$04$other', $this->at('2026-11-03 10:00:00')));

        $this->server->reload();

        self::assertSame(['front', RegisterRole::Main, 'counter'], [$repository->mainRegisterAt('store-1')?->code, $repository->findRegister('front')?->role(), $repository->findRegister('front')?->channel()]);
        self::assertSame(RegisterRole::Mobile, $repository->findRegister('phone-1')?->role());
        self::assertSame('kiosk', $repository->mainRegisterAt(null)?->code);
        self::assertNull($repository->mainRegisterAt('store-2'));
        self::assertSame([['t1', false], ['t2', true]], array_map(static fn (Terminal $terminal): array => [$terminal->code, $terminal->pinEntry], $repository->terminalsAt('front')));
        self::assertSame([], $repository->terminalsAt('phone-1'));
        self::assertSame(['tmr_0', false], [$repository->findTerminal('t0')?->device, $repository->findTerminal('t0')?->isActive()]);
        self::assertSame(['con', 'wholesale'], array_map(static fn (SalePreset $preset): string => $preset->code, $repository->activePresets()));
        $wholesale = $repository->findPreset('wholesale');
        self::assertSame(['Wholesale', 'b2b', 'resellers', true], [$wholesale?->label(), $wholesale?->channel(), $wholesale?->customerGroup(), $wholesale?->identifiesCustomer()]);

        $pin = $repository->findCashierPin('ada');
        self::assertSame(['$2y$04$hash', '2026-11-03 15:00:00'], [$pin?->hash(), $pin?->setAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
        self::assertInstanceOf(CashierPin::class, $pin);
        $repository->removeCashierPin($pin);
        $this->server->reload();

        self::assertNull($repository->findCashierPin('ada'));
        self::assertInstanceOf(CashierPin::class, $repository->findCashierPin('bob'));
    }

    public function testTheSessionsClosedOverAPeriodAreFoundInOrder(): void
    {
        $repository = $this->server->repository();
        $front = Register::of('front', 'Front', $this->cad(), 5);
        $back = Register::of('back', 'Back', $this->cad(), 5);
        $repository->addRegister($front);
        $repository->addRegister($back);

        $sessions = [];

        foreach ([[$front, '2026-11-03 18:00:00'], [$back, '2026-11-03 17:00:00'], [$front, '2026-11-04 18:00:00'], [$front, '2026-11-02 23:59:59'], [$back, null]] as $index => [$register, $closed]) {
            [$session, $float] = CashSession::open($register, 'ada', Money::of(1_000, $this->cad()), $this->at('2026-11-02 08:00:00'));
            $repository->addSession($session);
            $repository->addCashMovement($float);

            if (null !== $closed) {
                $session->close(Money::of(1_000 + $index, $this->cad()), 'ada', $this->at($closed));
            }

            $sessions[] = $session->id;
        }

        $this->server->reload();
        $from = $this->at('2026-11-03 00:00:00');
        $to = $this->at('2026-11-04 18:00:00');

        self::assertSame([$sessions[1], $sessions[0]], array_map(static fn (CashSession $session): string => $session->id, $repository->closedBetween($from, $to)));
        self::assertSame([$sessions[0]], array_map(static fn (CashSession $session): string => $session->id, $repository->closedBetween($from, $to, 'front')));
        self::assertSame([$sessions[0], $sessions[2]], array_map(static fn (CashSession $session): string => $session->id, $repository->closedBetween($from, $to->modify('+1 second'), 'front')));
        self::assertSame([], $repository->closedBetween($from, $to, 'nope'));
        self::assertSame($sessions[4], $repository->openSessionOf('back')?->id);
        self::assertSame([1, 1_000], [\count($repository->cashMovementsOf($sessions[0])), $repository->closedBetween($from, $to, 'front')[0]->expected->minorUnits]);
        self::assertSame('2026-11-03 23:00:00', $repository->findSession($sessions[0])?->closedAt()?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    public function testSalesPaymentsHeldSalesLayawaysAndTerminalPaymentsAreKept(): void
    {
        $repository = $this->server->repository();
        $front = Register::of('front', 'Front', $this->cad(), 5, null, null, RegisterRole::Main);
        $repository->addRegister($front);
        [$session, $float] = CashSession::open($front, 'ada', Money::of(0, $this->cad()), $this->at('2026-11-03 09:00:00'));
        $repository->addSession($session);
        $repository->addCashMovement($float);
        $repository->addCashMovement($session->move(CashMovementKind::Sale, Money::of(500, $this->cad()), Money::of(1, $this->cad()), 'ada', $this->at('2026-11-03 09:00:00'), self::PAY_1));
        $repository->addSale(RegisterSale::of('A-2', $session, 'ada', $this->at('2026-11-03 09:10:00'), Money::of(100, $this->cad()), 'con'));
        $repository->addSale(RegisterSale::of('A-1', $session, 'ada', $this->at('2026-11-03 09:05:00')));
        $repository->addPayment(SessionPayment::of(self::PAY_1, $session, 'A-1', 'cash', Money::of(499, $this->cad()), null, $this->at('2026-11-03 09:05:00')));
        $repository->hold(HeldSale::of('A-3', $front, 'Red coat', 'ada', $this->at('2026-11-03 09:20:00')));
        $repository->hold(HeldSale::of('A-4', $front, 'Blue hat', 'ada', $this->at('2026-11-03 09:15:00')));
        $repository->addLayaway(Layaway::of('L-2', 'customer-1', $this->at('2026-11-03 09:00:00'), $this->at('2026-12-01 00:00:00')));
        $repository->addLayaway(Layaway::of('L-1', 'customer-2', $this->at('2026-11-03 09:00:00'), $this->at('2026-12-01 00:00:00')));
        $repository->addLayaway(Layaway::of('L-3', 'customer-3', $this->at('2026-11-03 09:00:00'), $this->at('2026-12-02 00:00:00')));

        $terminal = Terminal::of('t1', $front, 'fake', 'tmr_1', 'One', true);
        $repository->addTerminal($terminal);
        $presented = TerminalPayment::of(self::PAY_2, $terminal, $session, 'fake_1_'.self::PAY_2, $this->at('2026-11-03 09:30:00'));
        $presented->count();

        $repository->addTerminalPayment($presented);
        $this->server->reload();

        self::assertSame([['A-1', 0, null], ['A-2', 100, 'con']], array_map(static fn (RegisterSale $sale): array => [$sale->orderNumber, $sale->tip()->minorUnits, $sale->preset], $repository->salesOf($session->id)));
        self::assertSame(499, $repository->findPayment(self::PAY_1)?->amount->minorUnits);
        self::assertSame([self::PAY_1], array_map(static fn (SessionPayment $payment): string => $payment->payment, $repository->paymentsOf($session->id)));
        self::assertSame(1, $repository->cashMovementOf(self::PAY_1)?->rounding->minorUnits);
        self::assertSame(['A-4', 'A-3'], array_map(static fn (HeldSale $sale): string => $sale->orderNumber, $repository->heldAt('front')));
        self::assertSame(['L-1', 'L-2'], array_map(static fn (Layaway $layaway): string => $layaway->orderNumber, $repository->layawaysDue($this->at('2026-12-01 00:00:00'))));
        self::assertSame([], $repository->layawaysDue($this->at('2026-11-30 23:59:59')));

        $kept = $repository->findTerminalPayment(self::PAY_2);
        self::assertSame(['t1', $session->id, 'fake_1_'.self::PAY_2, true], [$kept?->terminal, $kept?->session, $kept?->processorReference, $kept?->isCounted()]);
        self::assertSame('2026-11-03 14:30:00', $kept?->presentedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));

        $held = $repository->findHeld('A-3');
        self::assertInstanceOf(HeldSale::class, $held);
        $repository->release($held);
        $this->server->reload();
        self::assertSame(['A-4'], array_map(static fn (HeldSale $sale): string => $sale->orderNumber, $repository->heldAt('front')));
    }

    private function cad(): Currency
    {
        return Currency::of('CAD');
    }

    /**
     * A moment in Toronto's zone: the columns keep it in UTC.
     */
    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }
}
