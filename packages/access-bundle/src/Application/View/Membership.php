<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\View;

use DateTimeImmutable;
use Phil\AccessBundle\Domain\ValueObject\MemberId;

/**
 * A member of an access group, and when the membership ends, if it does.
 */
final readonly class Membership
{
    public function __construct(
        public MemberId $member,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
