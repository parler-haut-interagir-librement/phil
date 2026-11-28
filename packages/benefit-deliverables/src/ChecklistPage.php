<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

/**
 * A page of the checklist: its lines, member by member, and the member the next page starts after, or `null` when
 * this page is the last.
 */
final readonly class ChecklistPage
{
    /**
     * @param list<ChecklistItem> $items
     */
    public function __construct(
        public array $items,
        public ?string $next,
    ) {
    }
}
