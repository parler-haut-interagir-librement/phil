<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use DateTimeImmutable;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Customers' store credit: one balance per customer and currency, opened the first time credit is granted.
 *
 * A customer is named by whatever identifier the application gives it — an account, a tenant, an email.
 */
final readonly class StoreCredits
{
    public function __construct(
        private InstrumentRepository $instruments,
    ) {
    }

    public function grant(string $holder, Money $amount, DateTimeImmutable $at, ?string $note = null, ?string $orderNumber = null): Movement
    {
        $credit = $this->instruments->findStoreCredit($holder, $amount->currency->code);

        if (!$credit instanceof Instrument) {
            $credit = Instrument::storeCredit($holder, $amount->currency, $at);
            $this->instruments->addInstrument($credit);
        }

        $movement = $credit->credit(MovementKind::Granted, $amount, $at, $orderNumber, null, $note);
        $this->instruments->addMovement($movement);

        return $movement;
    }

    /**
     * What a customer can spend, zero when nothing was ever granted.
     */
    public function balance(string $holder, Currency $currency): Money
    {
        return $this->instruments->findStoreCredit($holder, $currency->code)->balance ?? Money::zero($currency);
    }

    public function of(string $holder, Currency $currency): ?Instrument
    {
        return $this->instruments->findStoreCredit($holder, $currency->code);
    }
}
