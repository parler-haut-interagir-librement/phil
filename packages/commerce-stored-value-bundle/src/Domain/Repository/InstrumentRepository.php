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
     * Stores at once, all or none, the gift cards an order issues, each with its `Issued` movement under its rank
     * among the order's cards — in a transaction of its own, or nested in the caller's. Returns false, storing none,
     * when a card of the same order and rank was stored first, by another request or another server.
     *
     * @param non-empty-list<array{Instrument, Movement}> $cards
     */
    public function addIssuedCards(array $cards): bool;

    /**
     * The movement an instrument recorded under an idempotency key, if any.
     */
    public function movementUnderKey(string $instrument, string $idempotencyKey): ?Movement;

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
