<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Contract;

use DateTimeImmutable;
use Phil\BenefitDeliverables\DeliverableKey;
use Phil\BenefitDeliverables\Delivery;
use Phil\BenefitDeliverables\Period;

/**
 * The deliverables marked done, one record per period, benefit and member.
 */
interface DeliverableStore
{
    /**
     * @param list<string> $members at most 1000
     *
     * @return list<Delivery> the deliveries of these members for the period, of any benefit
     */
    public function deliveries(Period $period, array $members): array;

    /**
     * Marks each deliverable done at `$at` by `$by`. A deliverable already done keeps its first delivery.
     *
     * @param list<DeliverableKey> $keys at most 1000
     *
     * @return int how many were marked now
     */
    public function markDone(array $keys, DateTimeImmutable $at, string $by): int;

    /**
     * Forgets the delivery of a deliverable marked by mistake; whether it was marked.
     */
    public function unmark(DeliverableKey $key): bool;
}
