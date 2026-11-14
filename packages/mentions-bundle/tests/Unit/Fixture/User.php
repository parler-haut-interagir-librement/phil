<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Fixture;

use Override;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Contract\VisitorPreferences;

/**
 * Nobody signed in, and a visitor without preferences.
 */
final class User implements CurrentUser, VisitorPreferences
{
    #[Override]
    public function id(): ?string
    {
        return null;
    }

    #[Override]
    public function values(): array
    {
        return [];
    }
}
