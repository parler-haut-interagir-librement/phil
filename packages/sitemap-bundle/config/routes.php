<?php

declare(strict_types=1);

use Phil\SitemapBundle\Infrastructure\Http\SitemapController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The sitemap served as it is asked for, imported by the application:
 *
 *     # config/routes/phil_sitemap.yaml
 *     phil_sitemap:
 *         resource: '@PhilSitemapBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(SitemapController::INDEX_ROUTE, '/sitemap.xml')
        ->controller([SitemapController::class, 'index'])
        ->methods(['GET']);
    $routes->add(SitemapController::FILE_ROUTE, '/sitemap.{name}.xml')
        ->controller([SitemapController::class, 'file'])
        ->requirements(['name' => '[a-z0-9][a-z0-9_-]{0,70}'])
        ->methods(['GET']);
};
