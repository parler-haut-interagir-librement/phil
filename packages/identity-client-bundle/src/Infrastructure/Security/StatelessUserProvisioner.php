<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Override;
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The default: no account, a user known by its subject alone, and nothing else kept in the session.
 */
final readonly class StatelessUserProvisioner implements UserProvisioner
{
    #[Override]
    public function provision(VerifiedIdentity $identity): UserInterface
    {
        return $this->user($identity->subject);
    }

    #[Override]
    public function find(string $subject): UserInterface
    {
        return $this->user($subject);
    }

    private function user(string $subject): OidcUser
    {
        return new OidcUser(userIdentifier: $subject, sub: $subject);
    }
}
