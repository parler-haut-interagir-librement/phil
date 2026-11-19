<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Override;
use Phil\GuardianConsent\Contract\GuardianLink;

/**
 * The link of an application that names no adult managing an account: only the guardians who decided on a consent may
 * withdraw it.
 */
final readonly class NoGuardianLink implements GuardianLink
{
    #[Override]
    public function manages(GuardianId $guardian, MemberId $member): bool
    {
        return false;
    }
}
