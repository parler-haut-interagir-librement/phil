<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Override;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The passkeys in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryPasskeyRepository implements PasskeyRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, Passkey> */
    private array $passkeys = [];

    #[Override]
    public function findByCredentialId(string $credentialId): ?Passkey
    {
        return self::copy($this->passkeys[Passkey::idOf($credentialId)] ?? null);
    }

    #[Override]
    public function forAccount(string $accountId): array
    {
        $passkeys = array_filter($this->passkeys, static fn (Passkey $passkey): bool => $passkey->accountId() === $accountId);
        usort($passkeys, static fn (Passkey $a, Passkey $b): int => [$a->createdAt(), $a->id()] <=> [$b->createdAt(), $b->id()]);

        return self::copies($passkeys);
    }

    #[Override]
    public function save(Passkey ...$passkeys): void
    {
        foreach ($passkeys as $passkey) {
            $this->passkeys[$passkey->id()] = clone $passkey;
        }
    }

    #[Override]
    public function remove(Passkey $passkey): void
    {
        unset($this->passkeys[$passkey->id()]);
    }

    #[Override]
    public function reset(): void
    {
        $this->passkeys = [];
    }
}
