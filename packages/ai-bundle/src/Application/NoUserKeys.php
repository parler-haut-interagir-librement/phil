<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Override;
use Phil\AiBundle\Application\Port\UserKeys;

/**
 * No one lends a key: every task runs on the application's.
 */
final readonly class NoUserKeys implements UserKeys
{
    #[Override]
    public function keyFor(string $owner, string $platform, string $purpose): ?string
    {
        return null;
    }
}
