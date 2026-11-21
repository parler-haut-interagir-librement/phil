<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceStoredValueBundle\Domain\Instrument\InstrumentKind;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;

/**
 * Instruments and movements, in memory.
 */
final class InMemoryInstruments implements InstrumentRepository
{
    /** @var array<string, Instrument> */
    public array $instruments = [];

    /** @var list<Movement> */
    public array $movements = [];

    #[Override]
    public function findInstrument(string $id): ?Instrument
    {
        return $this->instruments[$id] ?? null;
    }

    #[Override]
    public function findByCodeHash(string $codeHash): ?Instrument
    {
        return array_find($this->instruments, static fn (Instrument $instrument): bool => $instrument->codeHash === $codeHash);
    }

    #[Override]
    public function findStoreCredit(string $holder, string $currency): ?Instrument
    {
        return array_find($this->instruments, static fn (Instrument $instrument): bool => InstrumentKind::StoreCredit === $instrument->kind && $instrument->holder === $holder && $instrument->balance->currency->code === $currency);
    }

    #[Override]
    public function addInstrument(Instrument $instrument): void
    {
        $this->instruments[$instrument->id] = $instrument;
    }

    #[Override]
    public function addMovement(Movement $movement): void
    {
        $this->movements[] = $movement;
    }

    #[Override]
    public function addIssuedCards(array $cards): bool
    {
        foreach ($cards as [$card, $issued]) {
            $this->addInstrument($card);
            $this->addMovement($issued);
        }

        return true;
    }

    #[Override]
    public function movementUnderKey(string $instrument, string $idempotencyKey): ?Movement
    {
        return array_find($this->movements, static fn (Movement $movement): bool => $movement->instrument === $instrument && $movement->idempotencyKey === $idempotencyKey);
    }

    #[Override]
    public function redemptionOf(string $payment): ?Movement
    {
        return array_find($this->movements, static fn (Movement $movement): bool => $movement->payment === $payment && MovementKind::Redeemed === $movement->kind);
    }

    #[Override]
    public function issuedFor(string $orderNumber): int
    {
        return \count(array_filter($this->movements, static fn (Movement $movement): bool => $movement->orderNumber === $orderNumber && MovementKind::Issued === $movement->kind));
    }

    #[Override]
    public function cardsIssuedFor(string $orderNumber): array
    {
        $cards = [];

        foreach ($this->movements as $movement) {
            if ($movement->orderNumber === $orderNumber && MovementKind::Issued === $movement->kind && isset($this->instruments[$movement->instrument])) {
                $cards[] = $this->instruments[$movement->instrument];
            }
        }

        return $cards;
    }

    #[Override]
    public function movementsOf(string $instrument): array
    {
        return array_values(array_filter($this->movements, static fn (Movement $movement): bool => $movement->instrument === $instrument));
    }
}
