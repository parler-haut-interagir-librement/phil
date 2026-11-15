<?php

declare(strict_types=1);

use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Phil\ModerationBundle\Infrastructure\Http\StatementController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The statements of reasons, for the member each one is addressed to: import them behind the firewall that knows the
 * members.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(StatementController::LIST_ROUTE, '/')->controller([StatementController::class, 'list'])->methods(['GET']);
    $routes->add(StatementController::SHOW_ROUTE, '/{id}')
        ->controller([StatementController::class, 'show'])
        ->requirements(['id' => AdminController::HEX])
        ->methods(['GET']);
};
