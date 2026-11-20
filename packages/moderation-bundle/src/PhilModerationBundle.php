<?php

declare(strict_types=1);

namespace Phil\ModerationBundle;

use Override;
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ModerationBundle\Infrastructure\DependencyInjection\ModerationPass;
use Phil\ModerationBundle\Infrastructure\DependencyInjection\ModerationServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A single report queue for every kind of content — forum posts, comments, reactions, listings, messages, profiles —,
 * each package plugging in its own `ModerationTarget`, with the roles of the moderation and the history of their
 * decisions, the hook of the first-post sandbox and of the watched words, temporary hiding by trusted members, notes
 * of the moderation on members, a public journal that shows the role and never the name, and the incident mode of
 * `phil/incident-mode-bundle` offered to the role that runs it when that bundle is registered.
 *
 * ```yaml
 * phil_moderation:
 *     roles:
 *         - { name: moderator, attribute: ROLE_MODERATOR }
 *     sandbox:
 *         types: [forum.post]
 * ```
 */
final class PhilModerationBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_moderation';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ModerationPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info("Registers the bundle's services.")
                    ->defaultTrue()
                ->end()
                ->scalarNode('connection')
                    ->info('The DBAL connection of the tables, by its name in the Doctrine configuration.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('reasons')
                    ->info('The reasons a report form offers, in order; their labels are translated by code in the domain "phil_moderation".')
                    ->scalarPrototype()->end()
                    ->defaultValue(Reasons::DEFAULTS)
                    ->requiresAtLeastOneElement()
                ->end()
                ->arrayNode('roles')
                    ->info('The roles of the moderation, in the order they are tried: a member who holds several acts under the first. The public journal shows the name.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('attribute')->info('The security attribute that grants the role.')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('types')->info('The target types the role decides on; every type when empty.')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        ['name' => 'moderator', 'attribute' => 'ROLE_MODERATOR', 'types' => []],
                        ['name' => 'administrator', 'attribute' => 'ROLE_ADMIN', 'types' => []],
                    ])
                    ->requiresAtLeastOneElement()
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout')->info('The template the pages extend; it defines the blocks "title" and "main".')->defaultValue('@PhilModeration/layout.html.twig')->cannotBeEmpty()->end()
                        ->integerNode('page_size')->defaultValue(25)->min(1)->max(200)->end()
                    ->end()
                ->end()
                ->arrayNode('journal')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('page_size')->defaultValue(50)->min(1)->max(200)->end()
                    ->end()
                ->end()
                ->arrayNode('sandbox')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('types')->info('The target types whose first content of a member waits for the moderation; none when empty.')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->arrayNode('surge')
                    ->info('When reports come this fast, the queue proposes the incident mode of phil/incident-mode-bundle, when it is registered.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('reports')->defaultValue(25)->min(1)->end()
                        ->integerNode('within')->info('Seconds.')->defaultValue(3600)->min(60)->max(86400)->end()
                    ->end()
                ->end()
                ->arrayNode('statements')
                    ->info('The statements of reasons a decision that restricts the author of a content — hide, remove, warn — comes with.')
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('grounds')
                            ->info('What each reason rests on, by its code: the terms of the site or the law, and the provision a statement names by default. A reason not listed rests on the terms; "illegal" rests on the law.')
                            ->useAttributeAsKey('reason')
                            ->arrayPrototype()
                                ->children()
                                    ->enumNode('ground')->values(['terms', 'illegal'])->isRequired()->end()
                                    ->scalarNode('reference')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('terms_url')->info('The terms of the site, a path or an https address.')->defaultNull()->validate()->ifTrue(self::notAnAddress(...))->thenInvalid('%s is neither a path nor an https address.')->end()->end()
                        ->scalarNode('complaint_url')->info('Where a member contests a decision, a path or an https address.')->defaultNull()->validate()->ifTrue(self::notAnAddress(...))->thenInvalid('%s is neither a path nor an https address.')->end()->end()
                        ->arrayNode('out_of_court')
                            ->info('The out-of-court dispute settlement bodies the site accepts.')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('url')->isRequired()->validate()->ifTrue(self::notAnAddress(...))->thenInvalid('%s is neither a path nor an https address.')->end()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('notification_type')
                            ->info('The type of phil/notifications-bundle that tells a member a statement is addressed to them, declared by the application; no notification when null.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('page_size')->defaultValue(25)->min(1)->max(200)->end()
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    /**
     * Whether a configured address is something other than a path of the site or an https address: a scheme such as
     * `javascript:` never reaches a link.
     */
    public static function notAnAddress(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        if (!\is_string($value) || 1 === preg_match('/[\x00-\x20\x7F]/', $value)) {
            return true;
        }

        return !str_starts_with($value, 'https://') && (!str_starts_with($value, '/') || str_starts_with($value, '//'));
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('phil_moderation.enabled', true === $config['enabled']);

        if (true !== $config['enabled']) {
            return;
        }

        $builder->registerForAutoconfiguration(ModerationTarget::class)->addTag(ModerationServices::TARGET_TAG);
        ModerationServices::load($config, $builder);
    }
}
