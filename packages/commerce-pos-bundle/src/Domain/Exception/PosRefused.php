<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * The register refused an operation.
 */
final class PosRefused extends DomainException implements DomainError
{
    public static function unknownRegister(string $code): self
    {
        return new self(\sprintf('No active register is known as "%s".', $code));
    }

    public static function sessionAlreadyOpen(string $register): self
    {
        return new self(\sprintf('The register %s already has an open session.', $register));
    }

    public static function noOpenSession(string $register): self
    {
        return new self(\sprintf('The register %s has no open session.', $register));
    }

    public static function sessionClosed(string $session): self
    {
        return new self(\sprintf('The session %s is closed.', $session));
    }

    public static function notEnoughTendered(string $tendered, string $due): self
    {
        return new self(\sprintf('%s tendered is less than the %s due in cash.', $tendered, $due));
    }

    public static function notACart(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not a cart: only a cart is held, tipped or rung up.', $orderNumber));
    }

    public static function taxModeDiffers(string $orderNumber, string $session): self
    {
        return new self(\sprintf('The order %s is not priced as the session %s sells, taxes included or added: start it in the session first.', $orderNumber, $session));
    }

    public static function notHeld(string $orderNumber): self
    {
        return new self(\sprintf('No sale is held as %s.', $orderNumber));
    }

    public static function depositShort(string $orderNumber, string $paid, string $deposit): self
    {
        return new self(\sprintf('The layaway %s needs a deposit of %s; %s is paid.', $orderNumber, $deposit, $paid));
    }

    public static function notALayaway(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not an open layaway.', $orderNumber));
    }

    public static function notPlacedForLayaway(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: only a placed order is put aside.', $orderNumber));
    }

    public static function alreadyALayaway(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is already put aside.', $orderNumber));
    }

    public static function methodNotRecorded(string $method): self
    {
        return new self(\sprintf('"%s" is not a method the register records.', $method));
    }

    public static function notTakenHere(string $payment): self
    {
        return new self(\sprintf('The payment %s was not taken in cash at a register.', $payment));
    }

    public static function secondMainRegister(string $register, ?string $location): self
    {
        return new self(\sprintf('The location %s already has a main register: %s cannot be one too.', $location ?? '(none)', $register));
    }

    public static function unknownTerminal(string $code): self
    {
        return new self(\sprintf('No active terminal is known as "%s".', $code));
    }

    public static function pinTerminalAtMobile(string $terminal, string $register): self
    {
        return new self(\sprintf('The terminal %s takes PIN payments: only a main register has one, and %s is mobile.', $terminal, $register));
    }

    public static function terminalNotAtRegister(string $terminal, string $register): self
    {
        return new self(\sprintf('The terminal %s is not at the register %s.', $terminal, $register));
    }

    public static function noTerminalAdapter(string $adapter): self
    {
        return new self(\sprintf('No payment terminal adapter is named "%s".', $adapter));
    }

    public static function notPresented(string $payment): self
    {
        return new self(\sprintf('The payment %s was not presented on a terminal.', $payment));
    }

    public static function offlineAtMainOnly(string $register): self
    {
        return new self(\sprintf('Only a main register sells offline; %s is mobile.', $register));
    }

    public static function saleNumberTaken(string $orderNumber): self
    {
        return new self(\sprintf('The order %s exists already: an offline sale never picks up a sale started elsewhere.', $orderNumber));
    }

    public static function notResumableHere(string $orderNumber, string $register): self
    {
        return new self(\sprintf('The sale %s is not picked up at %s: at the register that held it, or at the main register of its location.', $orderNumber, $register));
    }

    public static function unknownPreset(string $code): self
    {
        return new self(\sprintf('No active sale preset is known as "%s".', $code));
    }

    public static function customerRequired(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is sold under a preset that identifies the customer: assign one first.', $orderNumber));
    }

    public static function notPaidForDraws(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not paid: its dice are rolled once it is.', $orderNumber));
    }

    public static function noDrawForm(string $drawer): self
    {
        return new self(\sprintf('No form at the register enters the draws of "%s".', $drawer));
    }
}
