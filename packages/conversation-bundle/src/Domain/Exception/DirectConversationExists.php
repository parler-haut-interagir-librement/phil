<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Exception;

use RuntimeException;

/**
 * A direct conversation started while its two participants already share one: another request started theirs first.
 * The store removes the conversation it was given, and `Conversations` writes in the one that exists.
 */
final class DirectConversationExists extends RuntimeException implements ConversationException
{
    public static function create(): self
    {
        return new self('These two participants already share a direct conversation.');
    }
}
