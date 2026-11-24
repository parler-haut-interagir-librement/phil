<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * A password hasher whose hashes and checks each take a slot of the semaphore; whether a hash needs computing again
 * costs nothing and takes none. It speaks the salted interface too, so that it wraps the `MigratingPasswordHasher`
 * Symfony builds for `sodium` or `auto`; a salt reaches only a hasher that takes one.
 */
final readonly class SemaphorePasswordHasher implements LegacyPasswordHasherInterface
{
    public function __construct(
        private PasswordHasherInterface $hasher,
        private PasswordCheckSemaphore $semaphore,
    ) {
    }

    #[Override]
    public function hash(#[SensitiveParameter] string $plainPassword, ?string $salt = null): string
    {
        $hasher = $this->hasher;

        return $this->semaphore->run(static fn (): string => $hasher instanceof LegacyPasswordHasherInterface ? $hasher->hash($plainPassword, $salt) : $hasher->hash($plainPassword));
    }

    #[Override]
    public function verify(string $hashedPassword, #[SensitiveParameter] string $plainPassword, ?string $salt = null): bool
    {
        $hasher = $this->hasher;

        return $this->semaphore->run(static fn (): bool => $hasher instanceof LegacyPasswordHasherInterface ? $hasher->verify($hashedPassword, $plainPassword, $salt) : $hasher->verify($hashedPassword, $plainPassword));
    }

    #[Override]
    public function needsRehash(string $hashedPassword): bool
    {
        return $this->hasher->needsRehash($hashedPassword);
    }
}
