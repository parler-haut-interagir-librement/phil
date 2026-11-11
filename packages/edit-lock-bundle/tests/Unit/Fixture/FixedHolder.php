<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Override;
use Phil\EditLockBundle\Application\HolderProvider;
use Phil\EditLockBundle\Domain\ValueObject\Holder;

final class FixedHolder implements HolderProvider
{
    public function __construct(
        public ?Holder $holder,
    ) {
    }

    #[Override]
    public function holder(): ?Holder
    {
        return $this->holder;
    }
}
