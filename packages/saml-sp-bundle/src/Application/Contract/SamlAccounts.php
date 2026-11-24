<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application\Contract;

use Phil\SamlSp\Attribute\SamlIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The accounts of the application for the people identity providers sign in: the account of a person already known,
 * and the account created at their first sign-in. Key the account on the identity provider and `subject()`, the stable
 * identifier the identity provider gives the person, never on the email address alone.
 */
interface SamlAccounts
{
    /**
     * The account of this person, null when they never signed in.
     */
    public function find(string $identityProvider, SamlIdentity $identity): ?UserInterface;

    /**
     * The account created for this person at their first sign-in, with the fields of the identity.
     */
    public function create(string $identityProvider, SamlIdentity $identity): UserInterface;
}
