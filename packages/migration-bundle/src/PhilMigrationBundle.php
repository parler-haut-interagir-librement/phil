<?php

declare(strict_types=1);

namespace Phil\MigrationBundle;

use Override;
use Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncementServices;
use Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncingMigrationsPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/migration` in a Symfony application: the migrations of DoctrineMigrationsBundle announce what they bring, and
 * each run of `doctrine:migrations:migrate` is announced once, on the chat channels of the configuration.
 */
final class PhilMigrationBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_migration';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('channels')
                    ->info('The channels a run is announced on, by name. Without any, the migrations still announce, and nothing is sent.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('transport')
                                ->info('The chatter transport of symfony/notifier, as framework.notifier.chatter_transports names it; the name of the channel when null.')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('locale')
                                ->info('The locale the announcement is read in: a language, and maybe a region.')
                                ->defaultValue('%kernel.default_locale%')
                                ->cannotBeEmpty()
                            ->end()
                            ->enumNode('content')
                                ->info('What the channel tells: the release notes, or the news.')
                                ->values(AnnouncementServices::CONTENTS)
                                ->defaultValue('notes')
                            ->end()
                            ->scalarNode('title')
                                ->info('The title of the first message, where {counter} becomes " (1/3)" when the release takes more than one; none when empty.')
                                ->defaultValue('')
                            ->end()
                            ->scalarNode('continued')
                                ->info('The title of the messages that follow, with the same {counter}; none when empty.')
                                ->defaultValue('')
                            ->end()
                            ->scalarNode('heading')
                                ->info('The line of a piece of news that holds its {title}.')
                                ->defaultValue('{title}')
                            ->end()
                            ->scalarNode('link')
                                ->info('The line of a piece of news that holds its {url}; none when empty.')
                                ->defaultValue('{url}')
                            ->end()
                            ->integerNode('max_length')
                                ->info('The longest message the transport takes: 2000 characters on Discord.')
                                ->defaultValue(2000)
                                ->min(AnnouncementServices::MIN_LENGTH)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AnnouncingMigrationsPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{channels: array<string, array{transport: ?string, locale: string, content: string, title: string, continued: string, heading: string, link: string, max_length: int}>} $config */
        AnnouncementServices::register($builder, $config['channels']);
    }
}
