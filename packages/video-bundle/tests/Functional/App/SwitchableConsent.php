<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Tests\Functional\App;

use Override;
use Phil\VideoBundle\Application\Contract\ViewingConsent;

/**
 * The consent manager of the application, which a test turns on or off.
 */
final class SwitchableConsent implements ViewingConsent
{
    public bool $granted = false;

    #[Override]
    public function granted(): bool
    {
        return $this->granted;
    }
}
