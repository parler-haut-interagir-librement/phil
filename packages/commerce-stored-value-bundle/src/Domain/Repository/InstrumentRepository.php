<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\Repository;

use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;

interface InstrumentRepository
{
    public function findInstrument(string $id): ?Instrument;

    public function findByCodeHash(string $codeHash): ?Instrument;

    public function findStoreCredit(string $holder, string $currency): ?Instrument;

    public function addInstrument(Instrument $instrument): void;

    public function addMovement(Movement $movement): void;

    /**
     * The redemption a payment made, if any.
     */
    public function redemptionOf(string $payment): ?Movement;

    /**
     * How many gift cards an order has issued.
     */
    public function issuedFor(string $orderNumber): int;

    /**
     * The gift cards an order issued, whatever their state.
     *
     * @return list<Instrument>
     */
    public function cardsIssuedFor(string $orderNumber): array;

    /**
     * An instrument's movements, oldest first.
     *
     * @return list<Movement>
     */
    public function movementsOf(string $instrument): array;
}
