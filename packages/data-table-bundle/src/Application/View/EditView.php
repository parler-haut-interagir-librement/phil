<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * The control of a cell being edited.
 */
final readonly class EditView
{
    /**
     * @param string           $input   `text`, `number`, `date` or `select`
     * @param list<ChoiceView> $choices
     * @param list<string>     $errors
     */
    public function __construct(
        public string $input,
        public string $value,
        public array $choices,
        public ?string $version,
        public array $errors,
        public string $cancelHref,
    ) {
    }
}
