<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Phil\DddBundle\Application\Bus\Command;

/**
 * The caller generates the identifier and passes it in, so that the command returns nothing.
 */
final readonly class RegisterMember implements Command
{
    public function __construct(
        public string $memberId,
        public string $email,
    ) {
    }
}
