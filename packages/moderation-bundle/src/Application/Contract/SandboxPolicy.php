<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * Whether a content of this member, of this type, waits for the moderation before it is published.
 */
interface SandboxPolicy
{
    public function holds(MemberId $author, string $targetType): bool;
}
