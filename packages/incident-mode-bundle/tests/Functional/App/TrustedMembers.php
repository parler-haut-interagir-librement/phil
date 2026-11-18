<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Functional\App;

use Override;
use Phil\IncidentMode\Contract\EstablishedMembers;

/**
 * The application's established members: those whose identifier starts with `trusted-`.
 */
final readonly class TrustedMembers implements EstablishedMembers
{
    #[Override]
    public function isEstablished(string $member): bool
    {
        return str_starts_with($member, 'trusted-');
    }
}
