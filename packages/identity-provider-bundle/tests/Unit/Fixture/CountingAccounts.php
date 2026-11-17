<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;

/**
 * The accounts in memory, counting the writes, for the tests that check what is saved and when.
 */
final class CountingAccounts implements AccountRepository
{
    public int $saves = 0;

    public function __construct(public readonly InMemoryAccountRepository $inner = new InMemoryAccountRepository())
    {
    }

    #[Override]
    public function get(string $id): ?Account
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function findByEmail(string $email): ?Account
    {
        return $this->inner->findByEmail($email);
    }

    #[Override]
    public function save(Account ...$accounts): void
    {
        ++$this->saves;
        $this->inner->save(...$accounts);
    }

    /**
     * @param Closure(Account): bool $change
     */
    #[Override]
    public function apply(Account $account, Closure $change): bool
    {
        $applied = $this->inner->apply($account, $change);

        if ($applied) {
            ++$this->saves;
        }

        return $applied;
    }

    #[Override]
    public function remove(Account $account): void
    {
        $this->inner->remove($account);
    }
}
