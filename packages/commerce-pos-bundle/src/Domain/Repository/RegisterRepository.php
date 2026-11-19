<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Repository;

use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\Terminal;

/**
 * What the shop sets up: its registers, their terminals, the sale presets and the cashiers' PINs.
 */
interface RegisterRepository
{
    public function findRegister(string $code): ?Register;

    public function addRegister(Register $register): void;

    /**
     * The active main register of a location — of registers that name none, when null —, if any.
     */
    public function mainRegisterAt(?string $location): ?Register;

    public function findTerminal(string $code): ?Terminal;

    public function addTerminal(Terminal $terminal): void;

    /**
     * @return list<Terminal> the active terminals of the register, by code
     */
    public function terminalsAt(string $register): array;

    public function findPreset(string $code): ?SalePreset;

    public function addPreset(SalePreset $preset): void;

    /**
     * @return list<SalePreset> the active presets, by code
     */
    public function activePresets(): array;

    public function findCashierPin(string $user): ?CashierPin;

    public function addCashierPin(CashierPin $pin): void;

    public function removeCashierPin(CashierPin $pin): void;
}
