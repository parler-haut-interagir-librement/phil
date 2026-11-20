<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommercePosBundle\Domain\Layaway\LayawayState;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\ImportedSale;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;

/**
 * Every repository of the register, in memory.
 */
final class InMemoryPos implements RegisterRepository, CashSessionRepository, RegisterSaleRepository
{
    /** @var array<string, Register> */
    public array $registers = [];

    /** @var array<string, CashSession> */
    public array $sessions = [];

    /** @var list<CashMovement> */
    public array $movements = [];

    /** @var list<RegisterSale> */
    public array $sales = [];

    /** @var array<string, SessionPayment> */
    public array $payments = [];

    /** @var array<string, HeldSale> */
    public array $held = [];

    /** @var array<string, Layaway> */
    public array $layaways = [];

    /** @var array<string, ImportedSale> */
    public array $imported = [];

    /** @var array<string, Terminal> */
    public array $terminals = [];

    /** @var array<string, TerminalPayment> */
    public array $terminalPayments = [];

    /** @var array<string, SalePreset> */
    public array $presets = [];

    /** @var array<string, CashierPin> */
    public array $pins = [];

    #[Override]
    public function mainRegisterAt(?string $location): ?Register
    {
        return array_find($this->registers, static fn (Register $register): bool => $register->isMain() && $register->isActive() && $register->location() === $location);
    }

    #[Override]
    public function findTerminal(string $code): ?Terminal
    {
        return $this->terminals[$code] ?? null;
    }

    #[Override]
    public function addTerminal(Terminal $terminal): void
    {
        $this->terminals[$terminal->code] = $terminal;
    }

    #[Override]
    public function terminalsAt(string $register): array
    {
        $terminals = array_filter($this->terminals, static fn (Terminal $terminal): bool => $terminal->register === $register && $terminal->isActive());
        ksort($terminals);

        return array_values($terminals);
    }

    #[Override]
    public function findPreset(string $code): ?SalePreset
    {
        return $this->presets[$code] ?? null;
    }

    #[Override]
    public function addPreset(SalePreset $preset): void
    {
        $this->presets[$preset->code] = $preset;
    }

    #[Override]
    public function activePresets(): array
    {
        $presets = array_filter($this->presets, static fn (SalePreset $preset): bool => $preset->isActive());
        ksort($presets);

        return array_values($presets);
    }

    #[Override]
    public function findCashierPin(string $user): ?CashierPin
    {
        return $this->pins[$user] ?? null;
    }

    #[Override]
    public function addCashierPin(CashierPin $pin): void
    {
        $this->pins[$pin->user] = $pin;
    }

    #[Override]
    public function removeCashierPin(CashierPin $pin): void
    {
        unset($this->pins[$pin->user]);
    }

    #[Override]
    public function closedBetween(DateTimeImmutable $from, DateTimeImmutable $to, ?string $register = null): array
    {
        $closed = array_filter($this->sessions, static function (CashSession $session) use ($from, $to, $register): bool {
            $at = $session->closedAt();

            return $at instanceof DateTimeImmutable && $at >= $from && $at < $to && (null === $register || $session->register === $register);
        });
        usort($closed, static fn (CashSession $a, CashSession $b): int => [$a->closedAt(), $a->id] <=> [$b->closedAt(), $b->id]);

        return $closed;
    }

    #[Override]
    public function findTerminalPayment(string $payment): ?TerminalPayment
    {
        return $this->terminalPayments[$payment] ?? null;
    }

    #[Override]
    public function addTerminalPayment(TerminalPayment $payment): void
    {
        $this->terminalPayments[$payment->payment] = $payment;
    }

    #[Override]
    public function findRegister(string $code): ?Register
    {
        return $this->registers[$code] ?? null;
    }

    #[Override]
    public function addRegister(Register $register): void
    {
        $this->registers[$register->code] = $register;
    }

    #[Override]
    public function findSession(string $id): ?CashSession
    {
        return $this->sessions[$id] ?? null;
    }

    #[Override]
    public function openSessionOf(string $register): ?CashSession
    {
        return array_find($this->sessions, static fn (CashSession $session): bool => $session->register === $register && $session->isOpen());
    }

    #[Override]
    public function addSession(CashSession $session): void
    {
        $this->sessions[$session->id] = $session;
    }

    #[Override]
    public function addCashMovement(CashMovement $movement): void
    {
        $this->movements[] = $movement;
    }

    #[Override]
    public function cashMovementsOf(string $session): array
    {
        return array_values(array_filter($this->movements, static fn (CashMovement $movement): bool => $movement->session === $session));
    }

    #[Override]
    public function cashMovementOf(string $payment): ?CashMovement
    {
        return array_find($this->movements, static fn (CashMovement $movement): bool => $movement->payment === $payment);
    }

    #[Override]
    public function addSale(RegisterSale $sale): void
    {
        $this->sales[] = $sale;
    }

    #[Override]
    public function salesOf(string $session): array
    {
        return array_values(array_filter($this->sales, static fn (RegisterSale $sale): bool => $sale->session === $session));
    }

    #[Override]
    public function addPayment(SessionPayment $payment): void
    {
        $this->payments[$payment->payment] = $payment;
    }

    #[Override]
    public function findPayment(string $payment): ?SessionPayment
    {
        return $this->payments[$payment] ?? null;
    }

    #[Override]
    public function paymentsOf(string $session): array
    {
        return array_values(array_filter($this->payments, static fn (SessionPayment $payment): bool => $payment->session === $session));
    }

    #[Override]
    public function findHeld(string $orderNumber): ?HeldSale
    {
        return $this->held[$orderNumber] ?? null;
    }

    #[Override]
    public function hold(HeldSale $sale): void
    {
        $this->held[$sale->orderNumber] = $sale;
    }

    #[Override]
    public function release(HeldSale $sale): void
    {
        unset($this->held[$sale->orderNumber]);
    }

    #[Override]
    public function heldAt(string $register): array
    {
        return array_values(array_filter($this->held, static fn (HeldSale $sale): bool => $sale->register === $register));
    }

    #[Override]
    public function findLayaway(string $orderNumber): ?Layaway
    {
        return $this->layaways[$orderNumber] ?? null;
    }

    #[Override]
    public function addLayaway(Layaway $layaway): void
    {
        $this->layaways[$layaway->orderNumber] = $layaway;
    }

    #[Override]
    public function layawaysDue(DateTimeImmutable $at): array
    {
        return array_values(array_filter($this->layaways, static fn (Layaway $layaway): bool => LayawayState::Open === $layaway->state() && $layaway->dueAt <= $at));
    }

    #[Override]
    public function findImported(string $id): ?ImportedSale
    {
        return $this->imported[$id] ?? null;
    }

    #[Override]
    public function addImported(ImportedSale $sale): void
    {
        $this->imported[$sale->id] = $sale;
    }
}
