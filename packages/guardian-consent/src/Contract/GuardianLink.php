<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Contract;

use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;

/**
 * Whether an adult manages a member's account, from a source the application names — a delegation of accounts, a
 * family. It widens who may withdraw a consent: a guardian who decided on one may, and so may an adult this link names.
 */
interface GuardianLink
{
    public function manages(GuardianId $guardian, MemberId $member): bool;
}
