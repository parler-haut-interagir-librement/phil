<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

use Phil\LocalPickup\HandOver;

/**
 * The journal of the orders handed over: who handed which order, where, when, and to whom when someone collects in the
 * customer's place.
 */
interface HandOverLog
{
    public function record(HandOver $handOver): void;

    /**
     * The hand-overs of a customer's orders, for the export of their personal data.
     *
     * @return list<HandOver>
     */
    public function ofCustomer(string $customer): array;

    /**
     * Forgets the names of the people who collected the customer's orders; the entries stay. Idempotent.
     */
    public function eraseCollectors(string $customer): void;
}
