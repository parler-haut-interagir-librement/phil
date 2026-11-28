<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Contract;

use Phil\ParticipantSafetyInfo\Role;

/**
 * Who the application designates for an event: its responsible people and its team.
 */
interface EventRoles
{
    /**
     * The role of an authenticated person for the event, `Role::None` when they have none.
     */
    public function roleOf(string $event, string $person): Role;
}
