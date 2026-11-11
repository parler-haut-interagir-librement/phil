<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Phil\DddBundle\Application\Bus\QueryHandler;

final readonly class FindMemberEmailHandler implements QueryHandler
{
    public function __construct(
        private MemberRegistry $members,
    ) {
    }

    /**
     * @throws UnknownMember
     */
    public function __invoke(FindMemberEmail $query): string
    {
        return $this->members->email(MemberId::fromString($query->memberId));
    }
}
