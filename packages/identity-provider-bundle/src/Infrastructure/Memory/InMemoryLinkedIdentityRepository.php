<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Override;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The linked identities in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryLinkedIdentityRepository implements LinkedIdentityRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, LinkedIdentity> */
    private array $identities = [];

    #[Override]
    public function find(string $provider, string $subject): ?LinkedIdentity
    {
        return self::copy($this->identities[LinkedIdentity::idOf($provider, $subject)] ?? null);
    }

    #[Override]
    public function forAccount(string $accountId): array
    {
        $identities = array_filter($this->identities, static fn (LinkedIdentity $identity): bool => $identity->accountId() === $accountId);
        usort($identities, static fn (LinkedIdentity $a, LinkedIdentity $b): int => [$a->linkedAt(), $a->id()] <=> [$b->linkedAt(), $b->id()]);

        return self::copies($identities);
    }

    #[Override]
    public function save(LinkedIdentity ...$identities): void
    {
        foreach ($identities as $identity) {
            $this->identities[$identity->id()] = clone $identity;
        }
    }

    #[Override]
    public function remove(LinkedIdentity $identity): void
    {
        unset($this->identities[$identity->id()]);
    }

    #[Override]
    public function reset(): void
    {
        $this->identities = [];
    }
}
