<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

/**
 * The text appears in one of the fields, whatever its case.
 */
final readonly class TextCriterion
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        public array $fields,
        public string $text,
    ) {
    }
}
