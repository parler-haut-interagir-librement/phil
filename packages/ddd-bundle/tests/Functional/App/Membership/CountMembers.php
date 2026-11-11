<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Phil\DddBundle\Application\Bus\Query;

/**
 * @implements Query<int>
 */
final readonly class CountMembers implements Query
{
}
