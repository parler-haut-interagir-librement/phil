<?php

declare(strict_types=1);

namespace Phil\SitemapBundle;

use DateTimeImmutable;
use Exception;
use Override;
use Phil\Sitemap\InvalidSitemap;
use Phil\Sitemap\Sitemap;
use Phil\SitemapBundle\Infrastructure\DependencyInjection\SitemapServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/sitemap` in a Symfony application: the pages of the routes that carry a `sitemap` option and of every
 * `UrlProvider`, served as `/sitemap.xml` and its files, or written with `phil:sitemap:dump`.
 */
final class PhilSitemapBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_sitemap';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_section')
                    ->info('The section of a route whose sitemap option names none.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $section): bool => !self::isSection($section))
                        ->thenInvalid('%s is not a section: 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit, not ending in an underscore and digits.')
                    ->end()
                ->end()
                ->integerNode('items_by_set')
                    ->info('The most pages a file holds; a section with more is cut into numbered parts.')
                    ->defaultValue(SitemapServices::MAX_PER_FILE)
                    ->min(1)
                    ->max(SitemapServices::MAX_PER_FILE)
                ->end()
                ->scalarNode('dump_directory')
                    ->info('Where phil:sitemap:dump writes the files when no directory is given.')
                    ->defaultValue('%kernel.project_dir%/public')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('cache_max_age')
                    ->info('The seconds a served sitemap may be kept by browsers and shared caches; 0 keeps it in none.')
                    ->defaultValue(3600)
                    ->min(0)
                ->end()
                ->booleanNode('allow_localhost')
                    ->info('Whether pages may be on localhost or under .localhost: true in development and tests only.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('route_options')
                    ->info('Whether the routes that carry a sitemap option make pages.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('defaults')
                    ->info("What a route's sitemap option falls back on.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('priority')
                            ->info('0.0 to 1.0, in steps of 0.1.')
                            ->defaultNull()
                            ->min(0.0)
                            ->max(1.0)
                            ->validate()
                                ->ifTrue(static fn (mixed $priority): bool => \is_float($priority) && round($priority, 1) !== $priority)
                                ->thenInvalid('A priority is 0.0 to 1.0, in steps of 0.1; %s is not.')
                            ->end()
                        ->end()
                        ->enumNode('changefreq')->values([null, ...SitemapServices::CHANGE_FREQUENCIES])->defaultNull()->end()
                        ->scalarNode('lastmod')
                            ->info('"now", or a date.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $lastmod): bool => null !== $lastmod && !self::isLastModified($lastmod))
                                ->thenInvalid('A lastmod is "now" or a date; %s is not.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private static function isSection(mixed $section): bool
    {
        try {
            return \is_string($section) && $section === Sitemap::checkSection($section);
        } catch (InvalidSitemap) {
            return false;
        }
    }

    /**
     * Whether a default `lastmod` is one `RouteUrlProvider` reads: "now", or a date PHP parses.
     */
    private static function isLastModified(mixed $lastmod): bool
    {
        if (!\is_string($lastmod)) {
            return false;
        }

        try {
            new DateTimeImmutable($lastmod);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{default_section: string, items_by_set: int, dump_directory: string, cache_max_age: int, route_options: bool, defaults: array{priority: ?float, changefreq: ?string, lastmod: ?string}, allow_localhost: bool} $config */
        SitemapServices::register($builder, $config);
    }
}
