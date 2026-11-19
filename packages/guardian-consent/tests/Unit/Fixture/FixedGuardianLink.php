<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit\Fixture;

use Override;
use Phil\GuardianConsent\Contract\GuardianLink;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;

/**
 * A link that names one adult as managing one member.
 */
final readonly class FixedGuardianLink implements GuardianLink
{
    public function __construct(
        private string $guardian,
        private string $member,
    ) {
    }

    #[Override]
    public function manages(GuardianId $guardian, MemberId $member): bool
    {
        return $guardian->value === $this->guardian && $member->value === $this->member;
    }
}
