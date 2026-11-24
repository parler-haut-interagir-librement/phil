<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Phil\SamlSp\Attribute\SamlIdentity;

/**
 * A sign-in completed: the identity the assertion gave, and the local path the person goes to.
 */
final readonly class CompletedLogin
{
    public function __construct(public SamlIdentity $identity, public string $target)
    {
    }
}
