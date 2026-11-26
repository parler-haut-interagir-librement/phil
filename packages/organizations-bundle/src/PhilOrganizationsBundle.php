<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle;

use Override;
use Phil\AccessLinks\TokenHasher;
use Phil\OrganizationsBundle\Application\Permission;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\OrganizationsBundle\Infrastructure\DependencyInjection\OrganizationsServices;
use Phil\OrganizationsBundle\Infrastructure\DependencyInjection\UnitServicesPass;
use Phil\OrganizationsBundle\Infrastructure\Http\UnitAdminController;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Organizations, their members and roles, teams and invitations, for anyone building their own identity and access
 * service:
 *
 * ```yaml
 * phil_organizations:
 *     invitations:
 *         hash_key: '%env(ORGANIZATIONS_INVITATION_KEY)%'
 *     roles:
 *         billing: { permissions: [billing.read, billing.pay] }
 * ```
 *
 * The bundle wires the store, the catalogues of roles, the services that change organizations, members, teams, units
 * and invitations, the tree of units, joining by a verified domain, acting on behalf of an organization, the voter, the
 * commands and the administration pages of the units; with `phil/privacy-bundle`, the exporter and eraser; with
 * `phil/access-bundle`, the relations and their tuples.
 */
final class PhilOrganizationsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_organizations';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->scalarNode('store')
                ->info('Where organizations are kept: "dbal", the tables of a Doctrine DBAL connection; "memory", for tests; or the id of a service implementing OrganizationStore, and UnitStore to keep units.')
                ->defaultValue(OrganizationsServices::STORE_DBAL)
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('connection')->info('The Doctrine DBAL connection of the tables.')->defaultValue('default')->cannotBeEmpty()->end()
            ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
            ->scalarNode('slugger')->info('Service id of the phil/slug Slugger that makes slugs from names; a transliterating slugger when null.')->defaultNull()->end()
            ->scalarNode('member_of_user')
                ->info('Service id of the MemberOfUser that gives the member a signed-in user is, for the voter: the user identifier, when it is a stable identifier, by default.')
                ->defaultNull()
            ->end()
            ->integerNode('recommended_owners')->info('The owners recommended per organization; fewer is a warning, never refused.')->defaultValue(2)->min(1)->end();

        $this->roles($children, 'roles', 'The roles of an organization besides owner, admin and member, by name, each with its permissions; listed under "admin" or "member", permissions add to those built-in roles. The owner holds every permission.', [RoleCatalog::OWNER]);
        $this->roles($children, 'team_roles', 'The roles of a team besides maintainer and member, by name, each with its permissions; listed under a built-in role, permissions add to it.', ['organization']);
        $this->roles($children, 'unit_roles', 'The roles of a unit besides manager and member, by name, each with its permissions, which hold in the unit and every unit below it; listed under a built-in role, permissions add to it.', []);
        $this->units($children);
        $this->invitations($children);
        $this->domainJoin($children);
        $this->access($children);
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [UnitAdminController::TOKEN_ID]]]);
        }
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new UnitServicesPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @phpstan-var array{store: string, connection: string, clock: string, slugger: string|null, member_of_user: string|null, recommended_owners: positive-int, roles: array<string, array{permissions: list<string>}>, team_roles: array<string, array{permissions: list<string>}>, unit_roles: array<string, array{permissions: list<string>}>, units: array{single_membership: bool, layout: string, kit_subdirectory: string|null}, invitations: array{hash_key: non-empty-list<string>, default_lifetime: positive-int, max_lifetime: positive-int, default_uses: positive-int, max_uses: positive-int, purge_retention: non-negative-int}, domain_join: array{enabled: bool, roles: list<string>}, access: array{enabled: bool|null, organization_kind: string, team_kind: string, unit_kind: string|null}} $config */
        OrganizationsServices::register($builder, $config);
    }

    /**
     * @param NodeBuilder<ArrayNodeDefinition> $children
     * @param list<string>                     $reserved
     */
    private function roles(NodeBuilder $children, string $name, string $info, array $reserved): void
    {
        $children
            ->arrayNode($name)
                ->info($info)
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->validate()
                    ->ifTrue(static fn (array $roles): bool => [] !== array_filter(array_keys($roles), static fn (int|string $role): bool => \in_array($role, $reserved, true) || 1 !== preg_match(Roles::NAME_PATTERN, (string) $role)))
                    ->thenInvalid(\sprintf('A role is 1 to 64 lowercase letters, digits or "_", starting with a letter%s: %%s', [] === $reserved ? '' : ', other than '.implode(', ', $reserved)))
                ->end()
                ->arrayPrototype()
                    ->children()
                        ->arrayNode('permissions')
                            ->info('The permissions of the role.')
                            ->scalarPrototype()
                                ->validate()
                                    ->ifTrue(static fn (mixed $permission): bool => !\is_string($permission) || 1 !== preg_match(Permission::NAME_PATTERN, $permission))
                                    ->thenInvalid('A permission is 1 to 128 lowercase letters, digits, "_", "." or "-", starting with a letter: %s')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param NodeBuilder<ArrayNodeDefinition> $children
     */
    private function invitations(NodeBuilder $children): void
    {
        $children
            ->arrayNode('invitations')
                ->isRequired()
                ->children()
                    ->arrayNode('hash_key')
                        ->info(\sprintf('The keys of the HMAC-SHA-256 each invitation token is kept as, at least %d bytes each, read from the secrets vault: "%%env(ORGANIZATIONS_INVITATION_KEY)%%", or a list whose first key hashes new tokens and whose others, the keys it replaced, still find the invitations made under them.', TokenHasher::MIN_KEY_LENGTH))
                        ->isRequired()
                        ->requiresAtLeastOneElement()
                        ->performNoDeepMerging()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $key): array => [$key])
                        ->end()
                        ->scalarPrototype()->cannotBeEmpty()->end()
                    ->end()
                    ->integerNode('default_lifetime')->info('The days an invitation lasts when its creator gives no expiry.')->defaultValue(7)->min(1)->end()
                    ->integerNode('max_lifetime')->info('The most days an invitation may last, at least default_lifetime.')->defaultValue(30)->min(1)->end()
                    ->integerNode('default_uses')->info('The uses an invitation allows when its creator says nothing.')->defaultValue(1)->min(1)->end()
                    ->integerNode('max_uses')->info('The most uses an invitation may allow, at least default_uses.')->defaultValue(100)->min(1)->max(Invitation::MAX_USES)->end()
                    ->integerNode('purge_retention')->info('The days an expired or revoked invitation is kept before phil:organizations:purge-invitations deletes it.')->defaultValue(30)->min(0)->max(36_500)->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $invitations): bool => $invitations['default_lifetime'] > $invitations['max_lifetime'] || $invitations['default_uses'] > $invitations['max_uses'])
                    ->thenInvalid('The default lifetime and uses of an invitation are at most its longest lifetime and its most uses.')
                ->end()
            ->end();
    }

    /**
     * @param NodeBuilder<ArrayNodeDefinition> $children
     */
    private function units(NodeBuilder $children): void
    {
        $children
            ->arrayNode('units')
                ->info('The units of an organization, in a tree.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('single_membership')->info('Whether a member belongs to one unit of an organization at most.')->defaultFalse()->end()
                    ->scalarNode('layout')->info('The template the administration pages of the units extend, which fills the blocks "title" and "main".')->defaultValue(UnitAdminController::LAYOUT)->cannotBeEmpty()->end()
                    ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are installed, under the components directory; the subdirectory of the ux_toolkit configuration when null.')->defaultNull()->end()
                ->end()
            ->end();
    }

    /**
     * @param NodeBuilder<ArrayNodeDefinition> $children
     */
    private function domainJoin(NodeBuilder $children): void
    {
        $children
            ->arrayNode('domain_join')
                ->info('Joining an organization by its verified email domain, off by default.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->defaultFalse()->end()
                    ->arrayNode('roles')
                        ->info('The roles a member joins with; never owner.')
                        ->scalarPrototype()->cannotBeEmpty()->end()
                        ->defaultValue([RoleCatalog::MEMBER])
                        ->requiresAtLeastOneElement()
                        ->validate()
                            ->ifTrue(static fn (array $roles): bool => \in_array(RoleCatalog::OWNER, $roles, true))
                            ->thenInvalid('Joining by a verified domain never grants the owner role.')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param NodeBuilder<ArrayNodeDefinition> $children
     */
    private function access(NodeBuilder $children): void
    {
        $children
            ->arrayNode('access')
                ->info('The memberships and roles published as relations and tuples of phil/access-bundle.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->info('true, false, or null to publish them whenever phil/access-bundle is installed.')->defaultNull()->end()
                    ->scalarNode('organization_kind')->info('The kind of the organizations in the access model.')->defaultValue('organization')->cannotBeEmpty()->end()
                    ->scalarNode('team_kind')->info('The kind of the teams in the access model.')->defaultValue('team')->cannotBeEmpty()->end()
                    ->scalarNode('unit_kind')
                        ->info('The kind of the units in the access model, or null to leave the units out of it.')
                        ->defaultValue('unit')
                        ->validate()
                            ->ifTrue(static fn (mixed $kind): bool => '' === $kind)
                            ->thenInvalid('The kind of the units is a name, or null to leave the units out of the access model.')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
