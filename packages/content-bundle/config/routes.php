<?php

declare(strict_types=1);

use Phil\ContentBundle\Infrastructure\Http\FeedController;
use Phil\ContentBundle\Infrastructure\Http\PreviewController;
use Phil\ContentBundle\Infrastructure\Http\PreviewLinks;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The feeds and the preview links, imported by the application:
 *
 *     # config/routes/phil_content.yaml
 *     phil_content:
 *         resource: '@PhilContentBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_content_feed', '/feeds/{resourceKey}.atom')
        ->controller(FeedController::class)
        ->requirements(['resourceKey' => '[a-z][a-z0-9_]{0,63}'])
        ->methods(['GET']);
    $routes->add(PreviewLinks::ROUTE, '/preview/{reference}/{locale}')
        ->controller(PreviewController::class)
        ->requirements(['reference' => '[a-z][a-z0-9_]{0,63}:[A-Za-z0-9_-]{1,64}', 'locale' => '[A-Za-z_]{2,12}'])
        ->methods(['GET']);
};
