<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * A column's heading.
 */
final readonly class HeaderView
{
    /**
     * @param string|null $ariaSort the value of `aria-sort`, on the sorted column only
     * @param string|null $nextSort the sort its button asks for, when the column sorts
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $ariaSort,
        public ?string $nextSort,
    ) {
    }
}
