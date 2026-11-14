<?php

declare(strict_types=1);

use Phil\OpenBadgesBundle\Infrastructure\Http\IssuerController;
use Phil\OpenBadgesBundle\Infrastructure\Http\StatusListController;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The issuer's profile and keys, and its status lists, imported by the application under the prefix its issuer's
 * identifier names:
 *
 *     # config/routes/phil_open_badges.yaml
 *     phil_open_badges:
 *         resource: '@PhilOpenBadgesBundle/config/routes.php'
 *         prefix: /open-badges
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(IssuerController::PROFILE_ROUTE, '/issuer')
        ->controller([IssuerController::class, 'profile'])
        ->methods(['GET']);
    $routes->add(IssuerController::KEYS_ROUTE, '/issuer/keys')
        ->controller([IssuerController::class, 'keys'])
        ->methods(['GET']);
    $routes->add(StatusListUrls::ROUTE, '/status/{list}')
        ->controller([StatusListController::class, 'show'])
        ->requirements(['list' => StatusListUrls::LIST])
        ->methods(['GET']);
};
