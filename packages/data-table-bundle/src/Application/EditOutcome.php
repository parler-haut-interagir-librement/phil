<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

/**
 * How an edit went, with the messages to show when it did not go through.
 */
final readonly class EditOutcome
{
    /**
     * @param list<string> $errors translated
     */
    public function __construct(
        public EditStatus $status,
        public array $errors = [],
    ) {
    }

    public function isSaved(): bool
    {
        return EditStatus::Saved === $this->status;
    }
}
