<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Security;

use Override;
use Phil\OrganizationsBundle\Application\Contract\MemberOfUser;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user identifier of the security token as the member, when it is a stable identifier: a user identified by an
 * email address is no member here, and the application names its own `MemberOfUser`.
 */
final readonly class UserIdentifierMember implements MemberOfUser
{
    #[Override]
    public function memberId(UserInterface $user): ?MemberId
    {
        try {
            return MemberId::fromString($user->getUserIdentifier());
        } catch (InvalidOrganization) {
            return null;
        }
    }
}
