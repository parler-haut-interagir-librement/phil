<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Access;

use Override;
use Phil\AccessBundle\Application\Contract\ModelContributor;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;

/**
 * Adds the organizations to the access model of `phil/access-bundle`: the kind of the organizations, one relation per
 * organization role, given to members; the kind of the teams, its `organization`, and one relation per team role.
 *
 * ```text
 * organization: owner, admin, member, … = [member]
 * team:         organization = [organization]; maintainer, member, … = [member]
 * ```
 *
 * An application's own contributor builds on them: a relation of an item given to `organization#admin`, a permission
 * read `fromRelated('organization', 'member')`.
 */
final readonly class OrganizationRelations implements ModelContributor
{
    public const string MEMBER_KIND = 'member';

    public const string ORGANIZATION_RELATION = 'organization';

    public function __construct(
        private RoleCatalog $roles,
        private RoleCatalog $teamRoles,
        public string $organizationKind = 'organization',
        public string $teamKind = 'team',
    ) {
    }

    #[Override]
    public function contribute(array $types): array
    {
        foreach ([$this->organizationKind, $this->teamKind] as $kind) {
            if (isset($types[$kind])) {
                throw InvalidOrganization::because(\sprintf('The kind "%s" is already in the access model: name the kinds of the organizations otherwise.', $kind));
            }
        }

        if ($this->teamRoles->has(self::ORGANIZATION_RELATION)) {
            throw InvalidOrganization::because('A team role cannot be named "organization", the relation of a team to its organization.');
        }

        $types[$this->organizationKind] = TypeDefinition::of($this->organizationKind, $this->relations($this->roles));
        $types[$this->teamKind] = TypeDefinition::of($this->teamKind, [self::ORGANIZATION_RELATION => Rewrite::direct($this->organizationKind), ...$this->relations($this->teamRoles)]);

        return $types;
    }

    /**
     * @return array<string, Rewrite>
     */
    private function relations(RoleCatalog $roles): array
    {
        $relations = [];

        foreach ($roles->names() as $role) {
            $relations[$role] = Rewrite::direct(self::MEMBER_KIND);
        }

        return $relations;
    }
}
