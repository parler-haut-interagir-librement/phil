<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use Override;
use Phil\IncidentMode\Contract\EstablishedMembers;

/**
 * Holds nobody for established: the default until the application plugs its trust levels in, so that an incident
 * restricts everyone.
 */
final readonly class NoEstablishedMembers implements EstablishedMembers
{
    #[Override]
    public function isEstablished(string $member): bool
    {
        return false;
    }
}
