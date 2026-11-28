<?php

declare(strict_types=1);

use Phil\IdentityProviderGoogleBundle\Infrastructure\Http\RiscController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The push endpoint of Google's security events. The sign-in itself goes through the routes of
 * phil/identity-provider-bundle, /login/with/google and its callback.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(RiscController::ROUTE, '/risc')->controller(RiscController::class)->methods(['POST'])->stateless();
};
