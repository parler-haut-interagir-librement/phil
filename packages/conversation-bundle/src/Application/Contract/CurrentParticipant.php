<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application\Contract;

use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * Who is reading or writing, in the request being served: null when nobody is signed in.
 */
interface CurrentParticipant
{
    public function current(): ?ParticipantId;
}
