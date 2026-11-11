<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use Phil\EditLockBundle\Domain\ValueObject\Holder;

/**
 * Who the current request writes as, from the application's security; `null` for nobody, whom the endpoints refuse.
 */
interface HolderProvider
{
    public function holder(): ?Holder;
}
