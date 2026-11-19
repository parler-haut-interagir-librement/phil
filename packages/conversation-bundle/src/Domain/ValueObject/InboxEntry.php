<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\ValueObject;

use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Participation;

/**
 * A line of a participant's inbox: the conversation, the participant's place in it, and how many messages of the
 * others came since the participant last read it.
 */
final readonly class InboxEntry
{
    public function __construct(
        public Conversation $conversation,
        public Participation $participation,
        public int $unread,
    ) {
    }
}
