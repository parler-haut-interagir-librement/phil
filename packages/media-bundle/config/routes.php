<?php

declare(strict_types=1);

use Phil\MediaBundle\Infrastructure\Http\MediaController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The signed media URLs, imported by the application:
 *
 *     # config/routes/phil_media.yaml
 *     phil_media:
 *         resource: '@PhilMediaBundle/config/routes.php'
 *         prefix: /media
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_media_original', '/{id}/{version}/original')
        ->controller([MediaController::class, 'original'])
        ->requirements(['id' => '[0-9a-f-]{36}', 'version' => '\d+'])
        ->methods(['GET']);
    $routes->add('phil_media_format', '/{id}/{version}/{format}.{type}')
        ->controller([MediaController::class, 'format'])
        ->requirements(['id' => '[0-9a-f-]{36}', 'version' => '\d+', 'format' => '[a-z][a-z0-9_]*', 'type' => 'webp|avif|jpeg|png'])
        ->methods(['GET']);
    $routes->add('phil_media_format_width', '/{id}/{version}/{format}@{width}.{type}')
        ->controller([MediaController::class, 'format'])
        ->requirements(['id' => '[0-9a-f-]{36}', 'version' => '\d+', 'format' => '[a-z][a-z0-9_]*', 'width' => '[1-9]\d{0,3}', 'type' => 'webp|avif|jpeg|png'])
        ->methods(['GET']);
};
