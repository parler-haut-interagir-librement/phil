<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Phil\DddBundle\Application\Bus\QueryHandler;

/**
 * Registered without any tag: the marker interface puts it on the query bus.
 */
final readonly class CountMembersHandler implements QueryHandler
{
    public function __construct(
        private MemberRegistry $members,
    ) {
    }

    public function __invoke(CountMembers $query): int
    {
        return $this->members->count();
    }
}
