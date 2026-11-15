<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * The member of the current request, by their stable identifier, or `null` for a visitor.
 */
interface CurrentMember
{
    public function member(): ?MemberId;
}
