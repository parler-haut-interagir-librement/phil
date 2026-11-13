<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;

/**
 * Fills form fields, keyed by their fully qualified names (`address.city`): a text, a checkbox's state, a choice's
 * option, or a list of options for a list that selects several. The fields stay editable.
 */
final readonly class SetForm implements Stage
{
    /**
     * @param array<string, string|list<string>> $fields
     */
    public function __construct(public array $fields)
    {
        if ([] === $fields) {
            throw InvalidStage::because('Filling a form takes one field at least.');
        }

        if (\in_array('', array_map(trim(...), array_keys($fields)), true)) {
            throw InvalidStage::because('A form field has a name.');
        }
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::SetForm;
    }

    #[Override]
    public function options(): array
    {
        return ['fields' => $this->fields];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
