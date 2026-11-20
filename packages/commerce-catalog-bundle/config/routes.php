<?php

declare(strict_types=1);

use Phil\CommerceCatalogBundle\Infrastructure\Http\ProductFeedController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The files of the product feeds, at /product-feeds/{feed}, imported by the application whose feeds are written
 * outside its public directory:
 *
 *     # config/routes/phil_commerce_catalog.yaml
 *     phil_commerce_catalog:
 *         resource: '@PhilCommerceCatalogBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ProductFeedController::ROUTE, '/product-feeds/{feed}')
        ->controller(ProductFeedController::class)
        ->requirements(['feed' => '[A-Za-z0-9_-]{1,64}'])
        ->methods(['GET', 'HEAD']);
};
