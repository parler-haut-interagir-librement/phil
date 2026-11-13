<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Contract;

use Phil\AccessBundle\Domain\ValueObject\MemberId;

/**
 * The member making the request, by a stable identifier, or null for a visitor.
 */
interface CurrentMember
{
    public function current(): ?MemberId;
}
