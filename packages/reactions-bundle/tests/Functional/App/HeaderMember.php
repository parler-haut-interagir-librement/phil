<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Functional\App;

use Override;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Application\Contract\MemberNames;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;
use Phil\ReactionsBundle\Application\ReactionSubject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The member a request names in a header, every reaction granted but the one called `refused`, and each member named
 * after their identifier.
 */
final readonly class HeaderMember implements CurrentMember, ReactionAccess, MemberNames
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        $request = $this->requests->getMainRequest();

        $member = $request instanceof Request ? $request->headers->get('X-Member') : null;

        return '' === $member ? null : $member;
    }

    #[Override]
    public function canReact(ReactionSubject $subject): bool
    {
        return 'refused' !== $subject->reaction->code;
    }

    #[Override]
    public function names(array $members): array
    {
        return array_combine($members, array_map(ucfirst(...), $members));
    }
}
