<?php

declare(strict_types=1);

use Phil\MapBundle\Application\Tiles\ProxiedTiles;
use Phil\MapBundle\Infrastructure\Http\TileProxyController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The tile proxy, imported under a prefix of the application's choice: its path ends with `{z}/{x}/{y}`.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ProxiedTiles::ROUTE, '/tiles/{z}/{x}/{y}')
        ->controller(TileProxyController::class)
        ->methods(['GET', 'HEAD'])
        ->requirements(['z' => '\d{1,2}', 'x' => '\d{1,7}', 'y' => '\d{1,7}'])
        ->stateless();
};
