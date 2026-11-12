<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Contract;

use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Turns a verified identity into the user of the application.
 *
 * The account is keyed on the issuer and the subject, never on an e-mail address: an address changes hands, a subject
 * does not. An existing account may only be linked through {@see VerifiedIdentity::verifiedEmail()}.
 */
interface UserProvisioner
{
    /**
     * Creates or updates the user of a sign-in that just succeeded.
     */
    public function provision(VerifiedIdentity $identity): UserInterface;

    /**
     * Returns the user of a subject on a later request, or null when the account no longer exists.
     */
    public function find(string $subject): ?UserInterface;
}
