<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * Who may hide a content until the moderation reviews it: the members of a high trust level.
 */
interface TrustedHiders
{
    public function canHide(MemberId $member): bool;
}
