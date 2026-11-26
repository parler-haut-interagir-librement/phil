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
 * organization role, given to members; the kind of the teams, its `organization`, and one relation per team role; with
 * unit roles, the kind of the units, its `organization`, its `parent`, and one relation per unit role, given to members
 * and held below: a unit role held in the parent holds in the unit.
 *
 * ```text
 * organization: owner, admin, member, … = [member]
 * team:         organization = [organization]; maintainer, member, … = [member]
 * unit:         organization = [organization]; parent = [unit]; manager, member, … = [member] or parent#<role>
 * ```
 *
 * An application's own contributor builds on them: a relation of an item given to `organization#admin`, a permission
 * read `fromRelated('organization', 'member')`.
 */
final readonly class OrganizationRelations implements ModelContributor
{
    public const string MEMBER_KIND = 'member';

    public const string ORGANIZATION_RELATION = 'organization';

    public const string PARENT_RELATION = 'parent';

    /**
     * The kind of the units, or null when the units are left out of the access model.
     */
    public ?string $unitKind;

    private RoleCatalog $unitRoles;

    /**
     * @param RoleCatalog|null $unitRoles the unit roles, or null to leave the units out of the access model
     */
    public function __construct(
        private RoleCatalog $roles,
        private RoleCatalog $teamRoles,
        public string $organizationKind = 'organization',
        public string $teamKind = 'team',
        ?RoleCatalog $unitRoles = null,
        ?string $unitKind = 'unit',
    ) {
        $this->unitKind = $unitRoles instanceof RoleCatalog ? $unitKind : null;
        $this->unitRoles = $unitRoles ?? RoleCatalog::unit();
    }

    #[Override]
    public function contribute(array $types): array
    {
        foreach ([$this->organizationKind, $this->teamKind, ...(null === $this->unitKind ? [] : [$this->unitKind])] as $kind) {
            if (isset($types[$kind])) {
                throw InvalidOrganization::because(\sprintf('The kind "%s" is already in the access model: name the kinds of the organizations otherwise.', $kind));
            }
        }

        if ($this->teamRoles->has(self::ORGANIZATION_RELATION)) {
            throw InvalidOrganization::because('A team role cannot be named "organization", the relation of a team to its organization.');
        }

        $types[$this->organizationKind] = TypeDefinition::of($this->organizationKind, $this->relations($this->roles));
        $types[$this->teamKind] = TypeDefinition::of($this->teamKind, [self::ORGANIZATION_RELATION => Rewrite::direct($this->organizationKind), ...$this->relations($this->teamRoles)]);

        if (null !== $this->unitKind) {
            $types[$this->unitKind] = $this->unitType($this->unitKind);
        }

        return $types;
    }

    private function unitType(string $kind): TypeDefinition
    {
        foreach ([self::ORGANIZATION_RELATION, self::PARENT_RELATION] as $relation) {
            if ($this->unitRoles->has($relation)) {
                throw InvalidOrganization::because(\sprintf('A unit role cannot be named "%s", a relation of a unit in the access model.', $relation));
            }
        }

        $relations = [self::ORGANIZATION_RELATION => Rewrite::direct($this->organizationKind), self::PARENT_RELATION => Rewrite::direct($kind)];

        foreach ($this->unitRoles->names() as $role) {
            $relations[$role] = Rewrite::union(Rewrite::direct(self::MEMBER_KIND), Rewrite::fromRelated(self::PARENT_RELATION, $role));
        }

        return TypeDefinition::of($kind, $relations);
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
