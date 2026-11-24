<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application\Exception;

use Phil\SamlSp\Exception\SamlException;
use RuntimeException;

/**
 * A person the identity provider signed in, refused by the application: no account, and the identity provider may not
 * create one at a first sign-in.
 */
final class AccountRefused extends RuntimeException implements SamlException
{
    public static function noAccount(string $identityProvider): self
    {
        return new self(\sprintf('The person has no account, and the identity provider "%s" may not create one.', $identityProvider));
    }
}
