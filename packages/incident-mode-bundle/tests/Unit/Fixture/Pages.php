<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Fixture;

use Phil\IncidentModeBundle\Attribute\SuspendedDuring;

/**
 * Controllers that carry the attribute: the sign-up refused on posting, the invitations on writing.
 */
final class Pages
{
    #[SuspendedDuring('signups', methods: ['POST'])]
    public function signUp(): string
    {
        return 'Welcome';
    }

    #[SuspendedDuring(['signups', 'invitations'])]
    #[SuspendedDuring('new-posts')]
    public function both(): string
    {
        return 'Both';
    }

    #[SuspendedDuring('invitations', methods: ['POST', 'PUT'])]
    #[SuspendedDuring('read-only', methods: ['DELETE'])]
    public function invite(): string
    {
        return 'Invited';
    }
}
