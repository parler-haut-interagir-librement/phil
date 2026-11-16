<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;

/**
 * The signing keys in memory, where another instance runs between the read of the keys and what follows it, for the
 * tests of concurrent use.
 */
final class InterleavedKeys implements SigningKeyRepository
{
    /**
     * Runs once, right after the next read of every key, before the caller sees the keys read.
     */
    public ?Closure $meanwhile = null;

    public function __construct(public readonly InMemorySigningKeyRepository $inner = new InMemorySigningKeyRepository())
    {
    }

    #[Override]
    public function all(): array
    {
        $keys = $this->inner->all();
        $meanwhile = $this->meanwhile;
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }

        return $keys;
    }

    #[Override]
    public function get(string $kid): ?SigningKey
    {
        return $this->inner->get($kid);
    }

    #[Override]
    public function save(SigningKey ...$keys): void
    {
        $this->inner->save(...$keys);
    }

    #[Override]
    public function remove(SigningKey $key): void
    {
        $this->inner->remove($key);
    }
}
