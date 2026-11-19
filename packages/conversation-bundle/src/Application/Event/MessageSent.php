<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application\Event;

/**
 * A message kept, by identifiers only, never its body: the conversation, the message and its sequence, the author, the
 * active participants who receive it, and among them those to notify — not muted, and not having blocked the author.
 */
final readonly class MessageSent
{
    /**
     * @param list<string> $recipients the active participants other than the author
     * @param list<string> $notified   the recipients to notify
     */
    public function __construct(
        public string $conversation,
        public string $message,
        public int $sequence,
        public string $author,
        public array $recipients,
        public array $notified,
    ) {
    }
}
