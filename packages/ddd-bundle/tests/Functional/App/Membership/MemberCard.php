<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

/**
 * What the membership context hands over to a client: an identifier among scalars.
 */
final readonly class MemberCard
{
    public function __construct(
        public MemberId $id,
        public string $email,
    ) {
    }
}
