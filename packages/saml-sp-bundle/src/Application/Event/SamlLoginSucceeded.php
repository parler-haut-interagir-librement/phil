<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application\Event;

use Phil\SamlSp\Attribute\SamlIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A person signed in through an identity provider: the key of the identity provider, the identity the assertion gave
 * and the user of the application.
 */
final readonly class SamlLoginSucceeded
{
    public function __construct(public string $identityProvider, public SamlIdentity $identity, public UserInterface $user)
    {
    }
}
