<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use Phil\ApiKeysBundle\Infrastructure\Attribute\RequiresScope;

/**
 * A controller that requires `projects:read` everywhere, and `projects:write` too to update.
 */
#[RequiresScope('projects:read')]
final class ScopedController
{
    public function show(): string
    {
        return 'show';
    }

    #[RequiresScope('projects:write')]
    public function update(): string
    {
        return 'update';
    }
}
