<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application\Event;

/**
 * A conversation started, by identifiers only: the conversation, who started it and who was brought in.
 */
final readonly class ConversationStarted
{
    /**
     * @param list<string> $participants the others brought in
     */
    public function __construct(
        public string $conversation,
        public string $startedBy,
        public array $participants,
    ) {
    }
}
