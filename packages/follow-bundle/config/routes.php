<?php

declare(strict_types=1);

use Phil\FollowBundle\Infrastructure\Http\FollowController;
use Phil\FollowBundle\Infrastructure\Http\FollowGuard;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The forms of the bundle, posted by a browser with or without JavaScript, each to a signed address.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(FollowGuard::LEVEL, '/level')->controller([FollowController::class, 'level'])->methods(['POST']);
    $routes->add(FollowGuard::REQUEST, '/request')->controller([FollowController::class, 'request'])->methods(['POST']);
    $routes->add(FollowGuard::VISIBILITY, '/visibility')->controller([FollowController::class, 'visibility'])->methods(['POST']);
    $routes->add(FollowGuard::MUTUAL, '/mutual')->controller([FollowController::class, 'mutual'])->methods(['POST']);
};
