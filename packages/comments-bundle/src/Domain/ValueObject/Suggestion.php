<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\ValueObject;

use Phil\CommentsBundle\Domain\Status\SuggestionState;

/**
 * The replacement a thread proposes for its passage — empty to propose its removal — and where the decision on it
 * stands.
 */
final readonly class Suggestion
{
    public function __construct(
        public string $text,
        public SuggestionState $state,
    ) {
    }

    public function isPending(): bool
    {
        return SuggestionState::Pending === $this->state;
    }

    /**
     * @return array{text: string, state: string}
     */
    public function toArray(): array
    {
        return ['text' => $this->text, 'state' => $this->state->value];
    }
}
