<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

use Phil\ModerationBundle\Application\Moderator;

/**
 * The moderator of the current request, with the first of the configured roles they hold.
 */
interface CurrentModerator
{
    public function moderator(): ?Moderator;
}
