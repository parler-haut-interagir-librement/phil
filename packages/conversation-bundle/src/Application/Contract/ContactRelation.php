<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application\Contract;

use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * Whether two participants are contacts, as the application knows them — friends, followers, members of a same
 * group —, for the participants who receive messages from their contacts only.
 */
interface ContactRelation
{
    public function areContacts(ParticipantId $one, ParticipantId $other): bool;
}
