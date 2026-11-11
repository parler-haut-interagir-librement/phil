<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Infrastructure\DependencyInjection;

use Phil\Sitemap\Sitemap;
use Phil\Sitemap\UrlProvider;
use Phil\SitemapBundle\Infrastructure\Console\DumpSitemapsCommand;
use Phil\SitemapBundle\Infrastructure\Http\SitemapController;
use Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider;
use Phil\SitemapBundle\Infrastructure\Sitemaps;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the sitemap: the providers, tagged — the routes' own first —, the builder, the controller and the
 * dump command.
 *
 * @phpstan-type SitemapConfig array{default_section: string, items_by_set: int, dump_directory: string, cache_max_age: int, route_options: bool, defaults: array{priority: ?float, changefreq: ?string, lastmod: ?string}}
 */
final class SitemapServices
{
    public const string PROVIDER_TAG = 'phil_sitemap.url_provider';

    public const int MAX_PER_FILE = Sitemap::MAX_PER_FILE;

    /** @var list<string> */
    public const array CHANGE_FREQUENCIES = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /**
     * @param SitemapConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        // The pages of the routes come first: every provider of the application runs after them. The constraint is
        // ignored when `route_options` is off and no route provider is registered.
        $container->registerForAutoconfiguration(UrlProvider::class)->addTag(self::PROVIDER_TAG, ['after' => RouteUrlProvider::class]);

        if ($config['route_options']) {
            $container->register(RouteUrlProvider::class, RouteUrlProvider::class)
                ->setArguments([new Reference('router'), new Reference('clock'), $config['default_section'], $config['defaults']])
                ->addTag(self::PROVIDER_TAG);
        }

        $container->register(Sitemaps::class, Sitemaps::class)
            ->setArguments([new TaggedIteratorArgument(self::PROVIDER_TAG), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->setPublic(true);

        $container->register(SitemapController::class, SitemapController::class)
            ->setArguments([new Reference(Sitemaps::class), new Reference('router'), $config['items_by_set'], $config['cache_max_age']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        $container->register(DumpSitemapsCommand::class, DumpSitemapsCommand::class)
            ->setArguments([new Reference(Sitemaps::class), new Reference('router.request_context'), $config['dump_directory'], $config['items_by_set']])
            ->addTag('console.command');
    }
}
