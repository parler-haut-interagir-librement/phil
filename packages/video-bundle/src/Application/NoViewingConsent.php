<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Override;
use Phil\VideoBundle\Application\Contract\ViewingConsent;

/**
 * The default: no viewer agreed, so no viewing is counted.
 */
final readonly class NoViewingConsent implements ViewingConsent
{
    #[Override]
    public function granted(): bool
    {
        return false;
    }
}
