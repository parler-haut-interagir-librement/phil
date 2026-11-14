<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * The question asked before an action.
 */
final readonly class ConfirmationView
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        public string $action,
        public string $label,
        public string $question,
        public array $ids,
        public bool $destructive,
        public string $cancelHref,
    ) {
    }
}
