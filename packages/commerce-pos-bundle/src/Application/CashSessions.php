<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\Money\Money;

/**
 * Registers' sessions: opened on a float, cash paid in, paid out and dropped to the safe, closed on a count, and the
 * end-of-day report.
 */
final readonly class CashSessions
{
    public function __construct(
        private Registers $registers,
        private CashSessionRepository $sessions,
        private RegisterSaleRepository $sales,
    ) {
    }

    public function open(string $register, string $by, Money $float, DateTimeImmutable $at, bool $taxIncluded = false): CashSession
    {
        $till = $this->registers->get($register);

        if ($this->sessions->openSessionOf($till->code) instanceof CashSession) {
            throw PosRefused::sessionAlreadyOpen($till->code);
        }

        [$session, $movement] = CashSession::open($till, $by, $float, $at, $taxIncluded);
        $this->sessions->addSession($session);
        $this->sessions->addCashMovement($movement);

        return $session;
    }

    /**
     * The register's open session.
     */
    public function current(string $register): CashSession
    {
        return $this->sessions->openSessionOf($register) ?? throw PosRefused::noOpenSession($register);
    }

    public function get(string $session): CashSession
    {
        return $this->sessions->findSession($session) ?? throw PosRefused::sessionClosed($session);
    }

    public function paidIn(string $session, Money $amount, string $by, DateTimeImmutable $at, ?string $note = null): void
    {
        $this->move($session, CashMovementKind::PaidIn, $amount, $by, $at, $note);
    }

    public function paidOut(string $session, Money $amount, string $by, DateTimeImmutable $at, ?string $note = null): void
    {
        $this->move($session, CashMovementKind::PaidOut, $amount, $by, $at, $note);
    }

    public function drop(string $session, Money $amount, string $by, DateTimeImmutable $at, ?string $note = null): void
    {
        $this->move($session, CashMovementKind::Drop, $amount, $by, $at, $note);
    }

    public function close(string $session, Money $counted, string $by, DateTimeImmutable $at): SessionReport
    {
        $this->get($session)->close($counted, $by, $at);

        return $this->report($session);
    }

    /**
     * The report of a session, open — so far — or closed.
     */
    public function report(string $session): SessionReport
    {
        $shift = $this->get($session);
        $zero = Money::zero($shift->currency());
        $totals = array_fill_keys(array_map(static fn (CashMovementKind $kind): string => $kind->value, CashMovementKind::cases()), $zero);
        $rounding = $zero;

        foreach ($this->sessions->cashMovementsOf($shift->id) as $movement) {
            $totals[$movement->kind->value] = $totals[$movement->kind->value]->add($movement->amount->abs());
            $rounding = $rounding->add($movement->rounding);
        }

        $byMethod = [];

        foreach ($this->sales->paymentsOf($shift->id) as $payment) {
            $byMethod[$payment->method] = ($byMethod[$payment->method] ?? $zero)->add($payment->amount);
        }

        ksort($byMethod);
        $tips = $zero;
        $sales = $this->sales->salesOf($shift->id);

        foreach ($sales as $sale) {
            $tips = $tips->add($sale->tip());
        }

        return new SessionReport(
            $shift,
            $totals[CashMovementKind::Float->value],
            $totals[CashMovementKind::Sale->value],
            $totals[CashMovementKind::Refund->value],
            $totals[CashMovementKind::PaidIn->value],
            $totals[CashMovementKind::PaidOut->value],
            $totals[CashMovementKind::Drop->value],
            $rounding,
            $byMethod,
            $tips,
            \count($sales),
        );
    }

    private function move(string $session, CashMovementKind $kind, Money $amount, string $by, DateTimeImmutable $at, ?string $note): void
    {
        $shift = $this->get($session);
        $this->sessions->addCashMovement($shift->move($kind, $amount, Money::zero($shift->currency()), $by, $at, null, $note));
    }
}
