<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Support;

use Override;
use Phil\ParticipantSafetyInfo\Contract\EventRoles;
use Phil\ParticipantSafetyInfo\Role;

final class FixedRoles implements EventRoles
{
    /**
     * @param array<string, array<string, Role>> $roles by event, then person
     */
    public function __construct(
        public array $roles = [],
    ) {
    }

    #[Override]
    public function roleOf(string $event, string $person): Role
    {
        return $this->roles[$event][$person] ?? Role::None;
    }
}
