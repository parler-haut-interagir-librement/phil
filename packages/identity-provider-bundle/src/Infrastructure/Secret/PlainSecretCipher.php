<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Secret;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use SensitiveParameter;

/**
 * Secrets kept as they are, behind a `plain.` prefix, when no key management service is configured: the database
 * then holds them in clear, and its access controls are their only protection. The configuration's `secrets.kms_key`
 * turns on the encryption by `KeyManagementSecretCipher`; a value sealed by either is told apart by its prefix.
 */
final readonly class PlainSecretCipher implements SecretCipher
{
    public const string PREFIX = 'plain.';

    #[Override]
    public function seal(#[SensitiveParameter] string $plaintext, string $context): string
    {
        return self::PREFIX.$plaintext;
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        if (!str_starts_with($sealed, self::PREFIX)) {
            throw MalformedInput::because('The secret was sealed by a key management service that is not configured.');
        }

        return substr($sealed, \strlen(self::PREFIX));
    }
}
