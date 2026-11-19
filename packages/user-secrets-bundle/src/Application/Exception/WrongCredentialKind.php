<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Exception;

use LogicException;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;

/**
 * A secret read as a kind of credential it is not: an API key read as an OAuth token, or the other way round.
 */
final class WrongCredentialKind extends LogicException implements UserSecretsException
{
    public static function of(string $name, CredentialKind $expected, CredentialKind $actual): self
    {
        return new self(\sprintf('The secret "%s" is an %s, not an %s.', $name, $actual->value, $expected->value));
    }
}
