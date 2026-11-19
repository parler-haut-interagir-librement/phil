<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Exception;

use RuntimeException;

/**
 * A message refused by the reception settings of a recipient: whom they let write to them, or a block. The message
 * does not say which, so that a blocked person does not learn it.
 */
final class WritingRefused extends RuntimeException implements ConversationException
{
    public static function create(): self
    {
        return new self('This participant does not receive messages from you.');
    }
}
