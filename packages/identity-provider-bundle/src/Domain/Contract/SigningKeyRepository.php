<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;

/**
 * The keys the provider signs with.
 */
interface SigningKeyRepository
{
    /**
     * @return list<SigningKey> oldest first
     */
    public function all(): array;

    public function get(string $kid): ?SigningKey;

    public function save(SigningKey ...$keys): void;

    public function remove(SigningKey $key): void;
}
