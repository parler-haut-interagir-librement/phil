<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * The members whose first post the moderation approved, who leave the sandbox.
 */
interface SandboxRepository
{
    /**
     * @phpstan-impure
     */
    public function clearedAt(MemberId $member): ?DateTimeImmutable;

    /**
     * Records that the member left the sandbox, unless they already did.
     */
    public function clear(MemberId $member, DateTimeImmutable $at): void;

    public function forget(MemberId $member): bool;
}
