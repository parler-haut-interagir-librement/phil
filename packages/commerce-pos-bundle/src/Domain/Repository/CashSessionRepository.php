<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\CashSession;

interface CashSessionRepository
{
    public function findSession(string $id): ?CashSession;

    public function openSessionOf(string $register): ?CashSession;

    public function addSession(CashSession $session): void;

    public function addCashMovement(CashMovement $movement): void;

    /**
     * @return list<CashMovement> oldest first
     */
    public function cashMovementsOf(string $session): array;

    /**
     * The cash movement a payment made, if any.
     */
    public function cashMovementOf(string $payment): ?CashMovement;

    /**
     * @param string|null $register one register's sessions only, or every register's
     *
     * @return list<CashSession> the sessions closed from the first moment included to the second excluded, oldest first
     */
    public function closedBetween(DateTimeImmutable $from, DateTimeImmutable $to, ?string $register = null): array;
}
