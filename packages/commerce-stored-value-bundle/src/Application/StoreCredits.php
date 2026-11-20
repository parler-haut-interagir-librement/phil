<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use DateTimeImmutable;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;
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

    /**
     * Grants credit to a customer. Under an idempotency key — a message's, a return's —, a grant is made once: asked
     * again with the same key, it adds nothing and returns the movement first recorded.
     *
     * @throws InvalidValue when the key is not one, or was used for another amount
     */
    public function grant(string $holder, Money $amount, DateTimeImmutable $at, ?string $note = null, ?string $orderNumber = null, ?string $idempotencyKey = null): Movement
    {
        Instrument::checkIdempotencyKey($idempotencyKey);
        $credit = $this->instruments->findStoreCredit($holder, $amount->currency->code);

        if ($credit instanceof Instrument && null !== $idempotencyKey) {
            $recorded = Replays::of($this->instruments->movementUnderKey($credit->id, $idempotencyKey), $amount);

            if ($recorded instanceof Movement) {
                return $recorded;
            }
        }

        if (!$credit instanceof Instrument) {
            $credit = Instrument::storeCredit($holder, $amount->currency, $at);
            $this->instruments->addInstrument($credit);
        }

        $movement = $credit->credit(MovementKind::Granted, $amount, $at, $orderNumber, null, $note, $idempotencyKey);
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
