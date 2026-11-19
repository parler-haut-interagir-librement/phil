<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application\Contract;

use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The name the pages show for a participant, as the application knows its members.
 */
interface ParticipantDirectory
{
    public function displayName(ParticipantId $participant): string;
}
