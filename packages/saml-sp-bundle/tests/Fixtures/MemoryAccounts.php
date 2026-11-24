<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Fixtures;

use Override;
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSpBundle\Application\Contract\SamlAccounts;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Accounts kept in memory, keyed on the identity provider and the subject.
 */
final class MemoryAccounts implements SamlAccounts
{
    /** @var array<string, UserInterface> */
    public array $accounts = [];

    #[Override]
    public function find(string $identityProvider, SamlIdentity $identity): ?UserInterface
    {
        return $this->accounts[$identityProvider.'|'.$identity->subject()] ?? null;
    }

    #[Override]
    public function create(string $identityProvider, SamlIdentity $identity): UserInterface
    {
        return $this->accounts[$identityProvider.'|'.$identity->subject()] = new InMemoryUser('account-'.\count($this->accounts), null, ['ROLE_MEMBER']);
    }
}
