<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Benchmark;

use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Infrastructure\Security\OrganizationVoter;
use Phil\OrganizationsBundle\Infrastructure\Security\UserIdentifierMember;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * What a page pays for each check of a member's permission in an organization: the permissions of a set of roles from
 * the catalogue, the check on a store in memory, and the vote of `OrganizationVoter` from the security token.
 */
final readonly class PermissionCheckBench
{
    private Stage $stage;

    private Organization $acme;

    private OrganizationVoter $voter;

    private UsernamePasswordToken $token;

    private Roles $roles;

    public function __construct()
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->voter = new OrganizationVoter($this->stage->access, new UserIdentifierMember());
        $this->token = new UsernamePasswordToken(new InMemoryUser('bob', null), 'main');
        $this->roles = Roles::of(['admin', 'billing']);
    }

    public function benchPermissionsOfRoles(): void
    {
        $this->stage->roles->permissionsOf($this->roles);
    }

    public function benchCan(): void
    {
        $this->stage->access->can($this->acme->id, Stage::member('bob'), 'members.invite');
    }

    public function benchVote(): void
    {
        $this->voter->vote($this->token, $this->acme, ['organization:members.invite']);
    }
}
