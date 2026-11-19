<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application;

use Override;
use Phil\ConversationBundle\Application\Contract\ParticipantDirectory;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The directory of an application that names none: a participant is shown by their identifier.
 */
final readonly class IdentifierDirectory implements ParticipantDirectory
{
    #[Override]
    public function displayName(ParticipantId $participant): string
    {
        return $participant->value;
    }
}
