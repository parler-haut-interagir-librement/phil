<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application\Event;

use Phil\SamlSp\Attribute\SamlIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An account was created for a person at their first sign-in through an identity provider.
 */
final readonly class SamlAccountCreated
{
    public function __construct(public string $identityProvider, public SamlIdentity $identity, public UserInterface $user)
    {
    }
}
