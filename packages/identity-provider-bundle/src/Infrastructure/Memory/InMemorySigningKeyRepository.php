<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The signing keys in memory, for tests; emptied between requests of a worker.
 */
final class InMemorySigningKeyRepository implements SigningKeyRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, SigningKey> */
    private array $keys = [];

    #[Override]
    public function all(): array
    {
        $keys = $this->keys;
        usort($keys, static fn (SigningKey $a, SigningKey $b): int => [$a->createdAt(), $a->kid()] <=> [$b->createdAt(), $b->kid()]);

        return self::copies($keys);
    }

    #[Override]
    public function get(string $kid): ?SigningKey
    {
        return self::copy($this->keys[$kid] ?? null);
    }

    #[Override]
    public function save(SigningKey ...$keys): void
    {
        foreach ($keys as $key) {
            $this->keys[$key->kid()] = clone $key;
        }
    }

    #[Override]
    public function remove(SigningKey $key): void
    {
        unset($this->keys[$key->kid()]);
    }

    #[Override]
    public function reset(): void
    {
        $this->keys = [];
    }
}
