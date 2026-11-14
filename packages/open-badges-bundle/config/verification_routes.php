<?php

declare(strict_types=1);

use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The verification page, imported by the application where it wants it:
 *
 *     # config/routes/phil_open_badges.yaml
 *     phil_open_badges_verification:
 *         resource: '@PhilOpenBadgesBundle/config/verification_routes.php'
 *         prefix: /open-badges
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(VerificationController::ROUTE, '/verify')
        ->controller([VerificationController::class, 'page'])
        ->methods(['GET', 'POST']);
};
