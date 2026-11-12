<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle;

use Override;
use Phil\DirectoryBundle\Infrastructure\DependencyInjection\DirectoryServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A directory for a Symfony application: listings in genres, each a content of content-bundle whose versions keep
 * its history, written by their author and the editors the author names, published by the administrators.
 */
final class PhilDirectoryBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_directory';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('genres')
                    ->info('The genres of the directory, by key: a lowercase letter followed by lowercase letters, digits or underscores.')
                    ->useAttributeAsKey('key')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->info('The path the pages of the genre are served under: podcasts.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('title')->info('The title of the genre.')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('relations')
                    ->info('The relations between genres, by key: a listing of the genre "from" is related to listings of the genre "to".')
                    ->useAttributeAsKey('key')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('from')->info('The genre whose listings name the related ones: larp.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('to')->info('The genre of the related listings: larp_site.')->isRequired()->cannotBeEmpty()->end()
                            ->booleanNode('multiple')->info('Whether a listing names several related ones, or one at most.')->defaultTrue()->end()
                            ->scalarNode('title')->info('The title of the related listings on a listing of "from": Played at.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('inverse_title')->info('The title of the listings that name one of "to", on it: Larps played here.')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('admin_role')->info('The role that administers the directory: it publishes, and changes every listing.')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                ->booleanNode('publish_every_change')->info('Publish the change of an author or an editor at once, instead of waiting for an administrator.')->defaultFalse()->end()
                ->integerNode('page_size')->info('How many listings a page of a genre lists.')->defaultValue(50)->min(1)->max(500)->end()
                ->scalarNode('sitemap_locale')->info('The locale whose listings the sitemap lists.')->defaultValue('%kernel.default_locale%')->cannotBeEmpty()->end()
                ->booleanNode('allow_localhost')->info('Whether the pages of the sitemap may be on localhost or under .localhost: true in development and tests only.')->defaultFalse()->end()
                ->arrayNode('words')
                    ->info('The words of the addresses that write a listing.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('new')->defaultValue('new')->cannotBeEmpty()->end()
                        ->scalarNode('edit')->defaultValue('edit')->cannotBeEmpty()->end()
                        ->scalarNode('editors')->defaultValue('editors')->cannotBeEmpty()->end()
                        ->scalarNode('history')->defaultValue('history')->cannotBeEmpty()->end()
                        ->scalarNode('publish')->defaultValue('publish')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => [] !== self::unknownGenres($config))
                ->then(static fn (array $config): never => throw new InvalidConfigurationException(\sprintf('A relation joins genres of the directory; %s is not one.', implode(', ', self::unknownGenres($config)))))
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach (DirectoryServices::prepended($this->getPath()) as $extension => $config) {
            if ($builder->hasExtension($extension)) {
                $builder->prependExtensionConfig($extension, $config);
            }
        }
    }

    /**
     * The genres the relations name that the directory does not declare.
     *
     * @param array<array-key, mixed> $config
     *
     * @return list<string>
     */
    private static function unknownGenres(array $config): array
    {
        $genres = \is_array($config['genres'] ?? null) ? array_keys($config['genres']) : [];
        $unknown = [];

        foreach (\is_array($config['relations'] ?? null) ? $config['relations'] : [] as $relation) {
            foreach (\is_array($relation) ? [$relation['from'] ?? null, $relation['to'] ?? null] : [] as $genre) {
                if (\is_string($genre) && !\in_array($genre, $genres, true) && !\in_array($genre, $unknown, true)) {
                    $unknown[] = $genre;
                }
            }
        }

        return $unknown;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{genres: array<string, array{path: string, title: string}>, relations: array<string, array{from: string, to: string, multiple: bool, title: string, inverse_title: string}>, admin_role: string, publish_every_change: bool, page_size: int, sitemap_locale: string, allow_localhost: bool, words: array{new: string, edit: string, editors: string, history: string, publish: string}} $config */
        DirectoryServices::register($builder, $config);
    }
}
