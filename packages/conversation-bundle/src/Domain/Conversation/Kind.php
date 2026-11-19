<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Conversation;

/**
 * A conversation between two participants, or a group, which may take more.
 */
enum Kind: string
{
    case Direct = 'direct';
    case Group = 'group';
}
