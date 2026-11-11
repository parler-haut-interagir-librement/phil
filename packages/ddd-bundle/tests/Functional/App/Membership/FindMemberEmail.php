<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Phil\DddBundle\Application\Bus\Query;

/**
 * @implements Query<string>
 */
final readonly class FindMemberEmail implements Query
{
    public function __construct(
        public string $memberId,
    ) {
    }
}
