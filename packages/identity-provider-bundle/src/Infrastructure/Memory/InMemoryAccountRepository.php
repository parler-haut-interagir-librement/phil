<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Closure;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The accounts in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryAccountRepository implements AccountRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, Account> */
    private array $accounts = [];

    #[Override]
    public function get(string $id): ?Account
    {
        return self::copy($this->accounts[$id] ?? null);
    }

    #[Override]
    public function findByEmail(string $email): ?Account
    {
        $email = mb_strtolower(trim($email));

        return self::copy(array_find($this->accounts, static fn (Account $account): bool => $account->email() === $email));
    }

    #[Override]
    public function save(Account ...$accounts): void
    {
        foreach ($accounts as $account) {
            $this->accounts[$account->id()] = clone $account;
        }
    }

    /**
     * @param Closure(Account): bool $change
     */
    #[Override]
    public function apply(Account $account, Closure $change): bool
    {
        $stored = $this->get($account->id());

        if (!$stored instanceof Account || !$change($stored)) {
            return false;
        }

        $this->save($stored);

        return true;
    }

    #[Override]
    public function remove(Account $account): void
    {
        unset($this->accounts[$account->id()]);
    }

    #[Override]
    public function reset(): void
    {
        $this->accounts = [];
    }
}
