<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Contract;

use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The member a signed-in user is, for the voters: the opaque subject identifier of the application's user provider,
 * never an email address.
 */
interface MemberOfUser
{
    /**
     * @return MemberId|null null when the user is no member
     */
    public function memberId(UserInterface $user): ?MemberId;
}
