<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\MemberNames;

/**
 * What stands in until the application says how members are named: no name is shown.
 */
final readonly class NoMemberNames implements MemberNames
{
    #[Override]
    public function names(array $members): array
    {
        return [];
    }
}
