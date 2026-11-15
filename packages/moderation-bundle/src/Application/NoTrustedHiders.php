<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Override;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * Trusts nobody to hide a content: the default until the application plugs its trust levels in.
 */
final readonly class NoTrustedHiders implements TrustedHiders
{
    #[Override]
    public function canHide(MemberId $member): bool
    {
        return false;
    }
}
