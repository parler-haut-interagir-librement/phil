<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\Money\Currency;

/**
 * The tills of the shop and the terminals at them: one main register per location, the tablet at the counter, and
 * as many mobile ones as there are sellers with a phone.
 */
final readonly class Registers
{
    public function __construct(
        private RegisterRepository $registers,
    ) {
    }

    public function create(string $code, string $name, Currency $currency, int $cashIncrement = 1, ?string $location = null, ?string $channel = null, RegisterRole $role = RegisterRole::Mobile): Register
    {
        $register = Register::of($code, $name, $currency, $cashIncrement, $location, $channel, $role);
        $this->checkSoleMain($register, $role);
        $this->registers->addRegister($register);

        return $register;
    }

    /**
     * Makes a register the main one of its location, or a mobile one — once none of its terminals takes PIN payments.
     */
    public function assign(string $code, RegisterRole $role): Register
    {
        $register = $this->get($code);
        $this->checkSoleMain($register, $role);

        if (RegisterRole::Mobile === $role) {
            foreach ($this->registers->terminalsAt($register->code) as $terminal) {
                if ($terminal->pinEntry) {
                    throw PosRefused::pinTerminalAtMobile($terminal->code, $register->code);
                }
            }
        }

        $register->assign($role);

        return $register;
    }

    /**
     * An active register.
     */
    public function get(string $code): Register
    {
        $register = $this->registers->findRegister($code);

        if (!$register instanceof Register || !$register->isActive()) {
            throw PosRefused::unknownRegister($code);
        }

        return $register;
    }

    /**
     * The active main register of a location, if any.
     */
    public function mainAt(?string $location): ?Register
    {
        return $this->registers->mainRegisterAt($location);
    }

    /**
     * Registers a connected terminal at a register; one that takes PIN payments only at the main register.
     *
     * @param string $adapter the name of the adapter that speaks to its processor
     * @param string $device  the processor's identifier of the device
     */
    public function addTerminal(string $code, string $register, string $adapter, string $device, string $label, bool $pinEntry = true): Terminal
    {
        $till = $this->get($register);

        if ($pinEntry && !$till->isMain()) {
            throw PosRefused::pinTerminalAtMobile($code, $till->code);
        }

        $terminal = Terminal::of($code, $till, $adapter, $device, $label, $pinEntry);
        $this->registers->addTerminal($terminal);

        return $terminal;
    }

    /**
     * An active terminal.
     */
    public function terminal(string $code): Terminal
    {
        $terminal = $this->registers->findTerminal($code);

        if (!$terminal instanceof Terminal || !$terminal->isActive()) {
            throw PosRefused::unknownTerminal($code);
        }

        return $terminal;
    }

    /**
     * @return list<Terminal> the active terminals at the register
     */
    public function terminalsAt(string $register): array
    {
        return $this->registers->terminalsAt($register);
    }

    private function checkSoleMain(Register $register, RegisterRole $role): void
    {
        if (RegisterRole::Main !== $role) {
            return;
        }

        $main = $this->registers->mainRegisterAt($register->location());

        if ($main instanceof Register && $main->code !== $register->code) {
            throw PosRefused::secondMainRegister($register->code, $register->location());
        }
    }
}
