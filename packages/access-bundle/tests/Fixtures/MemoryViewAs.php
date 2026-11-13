<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use Override;
use Phil\AccessBundle\Application\Contract\ViewAsStore;
use Phil\AccessBundle\Domain\ValueObject\MemberId;

/**
 * The member viewed, kept in the object.
 */
final class MemoryViewAs implements ViewAsStore
{
    public ?MemberId $member = null;

    #[Override]
    public function get(): ?MemberId
    {
        return $this->member;
    }

    #[Override]
    public function set(?MemberId $member): void
    {
        $this->member = $member;
    }
}
