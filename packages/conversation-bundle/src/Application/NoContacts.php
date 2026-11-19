<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application;

use Override;
use Phil\ConversationBundle\Application\Contract\ContactRelation;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The relation of an application that knows no contacts: a participant who receives messages from their contacts only
 * receives none.
 */
final readonly class NoContacts implements ContactRelation
{
    #[Override]
    public function areContacts(ParticipantId $one, ParticipantId $other): bool
    {
        return false;
    }
}
