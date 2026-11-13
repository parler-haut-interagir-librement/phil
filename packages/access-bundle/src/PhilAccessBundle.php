<?php

declare(strict_types=1);

namespace Phil\AccessBundle;

use Override;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\AccessBundle\Infrastructure\DependencyInjection\AccessServices;
use Phil\AccessBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\AccessBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Per-item visibility, sharing levels, access groups and their entry points, self-expiring access and "view as this
 * member", every right a declared relation of the `phil/resource-permissions` model, decided by Symfony Security
 * voters — EasyAdmin's included — and drawn by the recipes of the PHIL UX kit.
 *
 * ```yaml
 * phil_access:
 *     item_kinds: [page, document]
 *     groups:
 *         supporters: { entry_points: [purchase, invitation], duration: P1M }
 * ```
 */
final class PhilAccessBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_access';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the tuples are kept: "dbal", or "memory" for tests.')
                    ->values(['dbal', 'memory'])
                    ->defaultValue('dbal')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the tuple table.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('table')
                    ->info('The name of the tuple table.')
                    ->cannotBeEmpty()
                    ->defaultValue(DbalTupleStore::TABLE)
                ->end()
                ->scalarNode('space')
                    ->info("The identifier of the site's space, whose members and admins the visibilities name.")
                    ->cannotBeEmpty()
                    ->defaultValue('main')
                ->end()
                ->arrayNode('item_kinds')
                    ->info('The kinds of items whose access the bundle decides: "page", "document"…')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('sharing_levels')
                    ->info('The sharing levels, the widest first; each includes those after it, and the last is what a visibility opens.')
                    ->scalarPrototype()->end()
                    ->defaultValue(SharingLevels::DEFAULT)
                ->end()
                ->enumNode('default_visibility')
                    ->info('The visibility of an item registered without one: private to its owner by default.')
                    ->values(array_map(static fn (Visibility $visibility): string => $visibility->value, Visibility::cases()))
                    ->defaultValue(Visibility::Owner->value)
                ->end()
                ->arrayNode('groups')
                    ->info('The access groups, by name: the entry points they accept and how long a membership lasts.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('entry_points')
                                ->info('How a member comes in: '.implode(', ', array_map(static fn (EntryPoint $entry): string => $entry->value, EntryPoint::cases())).'.')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->enumPrototype()->values(array_map(static fn (EntryPoint $entry): string => $entry->value, EntryPoint::cases()))->end()
                            ->end()
                            ->scalarNode('duration')
                                ->info('An ISO 8601 duration, "P1M"; a membership then ends on its own. None by default.')
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('budget')
                    ->info('The limits of one check or listing.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_depth')->min(1)->max(100_000)->defaultValue(32)->end()
                        ->integerNode('max_reads')->min(1)->max(100_000)->defaultValue(500)->end()
                        ->integerNode('max_fan_out')->min(1)->max(100_000)->defaultValue(200)->end()
                        ->integerNode('max_candidates')->min(1)->max(100_000)->defaultValue(1000)->end()
                    ->end()
                ->end()
                ->scalarNode('current_member')
                    ->info('The CurrentMember service; the user identifier of the security token when Security is there, nobody otherwise.')
                    ->defaultNull()
                ->end()
                ->arrayNode('view_as')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('redirect')
                            ->info('The path of the site the forms of "view as this member" go back to.')
                            ->cannotBeEmpty()
                            ->defaultValue('/')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('easyadmin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('actions')
                            ->info('Each EasyAdmin action and the sharing level it needs; for the default levels, detail: view, edit: edit, delete: manage.')
                            ->useAttributeAsKey('action')
                            ->scalarPrototype()->end()
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [ViewAsController::TOKEN_ID]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, table: string, space: string, item_kinds: list<string>, sharing_levels: list<string>, default_visibility: string, groups: array<string, array{entry_points: list<string>, duration: ?string}>, budget: array{max_depth: int, max_reads: int, max_fan_out: int, max_candidates: int}, current_member: ?string, view_as: array{redirect: string}, easyadmin: array{actions: ?array<string, string>}, kit_subdirectory: ?string} $config */
        AccessServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass(ViewAsController::class, ViewAsController::TOKEN_ID));
        $container->addCompilerPass(new KitSubdirectoryPass());
    }
}
