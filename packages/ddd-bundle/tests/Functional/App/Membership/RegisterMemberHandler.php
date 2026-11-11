<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Phil\DddBundle\Application\Bus\CommandHandler;

/**
 * Registered without any tag: the marker interface puts it on the command bus.
 */
final readonly class RegisterMemberHandler implements CommandHandler
{
    public function __construct(
        private MemberRegistry $members,
    ) {
    }

    public function __invoke(RegisterMember $command): void
    {
        $this->members->add(MemberId::fromString($command->memberId), $command->email);
    }
}
