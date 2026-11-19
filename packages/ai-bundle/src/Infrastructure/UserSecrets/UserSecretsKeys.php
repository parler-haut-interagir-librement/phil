<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\UserSecrets;

use Override;
use Phil\AiBundle\Application\Port\UserKeys;
use Phil\UserSecretsBundle\Application\Exception\SecretNotFound;
use Phil\UserSecretsBundle\Application\SecretAccess;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Exception\RevokedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * The person's keys in `phil/user-secrets-bundle`: for each platform that takes one, the name of the secret that
 * holds it. A missing or revoked secret is no key; a secret that does not open, or is not an API key, fails.
 */
final readonly class UserSecretsKeys implements UserKeys
{
    /**
     * @param array<string, string> $secretNames the secret's name, by platform
     */
    public function __construct(
        private UserSecrets $secrets,
        private array $secretNames,
    ) {
    }

    #[Override]
    public function keyFor(string $owner, string $platform, string $purpose): ?string
    {
        $name = $this->secretNames[$platform] ?? null;

        if (null === $name) {
            return null;
        }

        try {
            return $this->secrets->apiKey(SecretAccess::forTaskOf(Owner::fromString($owner), $purpose), SecretName::fromString($name))->key->reveal();
        } catch (SecretNotFound|RevokedSecret) {
            return null;
        }
    }
}
