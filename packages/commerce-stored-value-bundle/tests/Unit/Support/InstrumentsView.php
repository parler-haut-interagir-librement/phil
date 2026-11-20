<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;

/**
 * Another request's view of the same instruments, which a test extends to make it see something else — a count read
 * before another request stored its cards.
 */
abstract class InstrumentsView implements InstrumentRepository
{
    public function __construct(
        private readonly InstrumentRepository $instruments,
    ) {
    }

    #[Override]
    public function findInstrument(string $id): ?Instrument
    {
        return $this->instruments->findInstrument($id);
    }

    #[Override]
    public function findByCodeHash(string $codeHash): ?Instrument
    {
        return $this->instruments->findByCodeHash($codeHash);
    }

    #[Override]
    public function findStoreCredit(string $holder, string $currency): ?Instrument
    {
        return $this->instruments->findStoreCredit($holder, $currency);
    }

    #[Override]
    public function addInstrument(Instrument $instrument): void
    {
        $this->instruments->addInstrument($instrument);
    }

    #[Override]
    public function addMovement(Movement $movement): void
    {
        $this->instruments->addMovement($movement);
    }

    #[Override]
    public function addIssuedCards(array $cards): bool
    {
        return $this->instruments->addIssuedCards($cards);
    }

    #[Override]
    public function movementUnderKey(string $instrument, string $idempotencyKey): ?Movement
    {
        return $this->instruments->movementUnderKey($instrument, $idempotencyKey);
    }

    #[Override]
    public function redemptionOf(string $payment): ?Movement
    {
        return $this->instruments->redemptionOf($payment);
    }

    #[Override]
    public function issuedFor(string $orderNumber): int
    {
        return $this->instruments->issuedFor($orderNumber);
    }

    #[Override]
    public function cardsIssuedFor(string $orderNumber): array
    {
        return $this->instruments->cardsIssuedFor($orderNumber);
    }

    #[Override]
    public function movementsOf(string $instrument): array
    {
        return $this->instruments->movementsOf($instrument);
    }
}
