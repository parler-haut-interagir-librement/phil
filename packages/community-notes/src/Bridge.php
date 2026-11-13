<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

use Phil\Resource\ResourceReference;

/**
 * Two raters who usually disagree and gave a note the same verdict, with the history that shows they usually
 * disagree: the evidence of a decision.
 */
final readonly class Bridge
{
    public function __construct(
        public ResourceReference $first,
        public ResourceReference $second,
        public PairHistory $history,
    ) {
    }

    /**
     * @return array{first: string, second: string, shared: int, agreed: int}
     */
    public function toArray(): array
    {
        return [
            'first' => $this->first->toString(),
            'second' => $this->second->toString(),
            'shared' => $this->history->shared,
            'agreed' => $this->history->agreed,
        ];
    }
}
