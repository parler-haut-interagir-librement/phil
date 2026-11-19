<?php

declare(strict_types=1);

namespace App\Reactions;

use Phil\ReactionsBundle\Application\Contract\CurrentMember as ReactionsCurrentMember;

/**
 * The stable identifier of the signed-in member, as phil/reactions-bundle names them: never an email address. Until it
 * reads the application's user it names nobody, and every visitor only sees what is counted.
 */
final readonly class CurrentMember implements ReactionsCurrentMember
{
    public function current(): ?string
    {
        return null;
    }
}
