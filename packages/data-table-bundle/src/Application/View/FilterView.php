<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * The control of a filter.
 */
final readonly class FilterView
{
    /**
     * @param list<ChoiceView> $choices
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public string $value,
        public string $from,
        public string $until,
        public array $choices,
    ) {
    }
}
