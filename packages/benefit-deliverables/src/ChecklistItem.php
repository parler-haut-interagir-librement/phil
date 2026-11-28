<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

/**
 * A line of the checklist: a benefit owed to a member for a period, and its delivery once marked.
 */
final readonly class ChecklistItem
{
    public function __construct(
        public DeliverableKey $key,
        public Benefit $benefit,
        public ?Delivery $delivery,
    ) {
    }

    public function isDone(): bool
    {
        return $this->delivery instanceof Delivery;
    }
}
