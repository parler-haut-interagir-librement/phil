<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

use DateTimeImmutable;

/**
 * A deliverable marked done: when, and by whom on the team.
 */
final readonly class Delivery
{
    /**
     * @param string $by the identifier of the person on the team who marked it
     */
    public function __construct(
        public DeliverableKey $key,
        public DateTimeImmutable $at,
        public string $by,
    ) {
    }
}
