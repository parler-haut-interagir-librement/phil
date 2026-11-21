<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\IdentityClientBundle\Application\Contract\ReplayFloor;

final class FixedReplayFloor implements ReplayFloor
{
    public int $reads = 0;

    public function __construct(private readonly int $floor)
    {
    }

    #[Override]
    public function floor(): int
    {
        ++$this->reads;

        return $this->floor;
    }
}
