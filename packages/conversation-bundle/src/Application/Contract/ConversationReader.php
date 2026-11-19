<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application\Contract;

use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;

/**
 * The conversations read without the check that the reader takes part in them, for the packages built on this bundle
 * that decide access themselves — a help desk whose agents read the requests of their queues, a live chat whose staff
 * answer visitors. A page shown to a member reads through `Conversations`, which checks it.
 */
interface ConversationReader
{
    public function conversation(ConversationId $id): ?Conversation;

    /**
     * @param positive-int $limit
     *
     * @return list<Message>
     */
    public function messages(ConversationId $id, int $limit, ?int $before = null): array;

    /**
     * @return list<Participation>
     */
    public function participants(ConversationId $id): array;
}
