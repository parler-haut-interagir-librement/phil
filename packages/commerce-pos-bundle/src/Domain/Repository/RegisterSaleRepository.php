<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\ImportedSale;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;

interface RegisterSaleRepository
{
    public function addSale(RegisterSale $sale): void;

    /**
     * @return list<RegisterSale>
     */
    public function salesOf(string $session): array;

    public function addPayment(SessionPayment $payment): void;

    public function findPayment(string $payment): ?SessionPayment;

    /**
     * @return list<SessionPayment>
     */
    public function paymentsOf(string $session): array;

    public function findHeld(string $orderNumber): ?HeldSale;

    public function hold(HeldSale $sale): void;

    public function release(HeldSale $sale): void;

    /**
     * @return list<HeldSale> oldest first
     */
    public function heldAt(string $register): array;

    public function findLayaway(string $orderNumber): ?Layaway;

    public function addLayaway(Layaway $layaway): void;

    /**
     * @return list<Layaway> the open layaways due by then, soonest first
     */
    public function layawaysDue(DateTimeImmutable $at): array;

    public function findImported(string $id): ?ImportedSale;

    public function addImported(ImportedSale $sale): void;

    public function findTerminalPayment(string $payment): ?TerminalPayment;

    public function addTerminalPayment(TerminalPayment $payment): void;
}
