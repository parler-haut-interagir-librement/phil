<?php

declare(strict_types=1);

use Phil\VirtualClassroomBundle\Infrastructure\Http\JoinController;
use Phil\VirtualClassroomBundle\Infrastructure\Http\WebhookController;
use Phil\VirtualClassroomBundle\Infrastructure\Routing\RoutedCallbackUrls;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of the bundle, imported under a prefix of the application's choice — `/virtual-classroom` in the
 * recipe —: the join link of a room, the webhook of a room for each channel, and the one webhook of an account.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(JoinController::ROUTE, '/{room}/join')
        ->controller(JoinController::class)
        ->methods(['GET'])
        ->requirements(['room' => '[a-z0-9][a-z0-9_-]{1,63}']);

    $routes->add(RoutedCallbackUrls::ROUTE, '/webhook/{provider}/{room}/{channel}/{token}')
        ->controller(WebhookController::class)
        ->methods(['GET', 'POST'])
        ->requirements(['provider' => '[a-z0-9][a-z0-9_.-]{0,63}', 'room' => '[a-z0-9][a-z0-9_-]{1,63}', 'channel' => 'ended|events', 'token' => '[A-Za-z0-9_-]{32}']);

    $routes->add(WebhookController::ROUTE, '/webhook/{provider}/{token}')
        ->controller(WebhookController::class)
        ->methods(['POST'])
        ->requirements(['provider' => '[a-z0-9][a-z0-9_.-]{0,63}', 'token' => '[A-Za-z0-9_-]{32}']);
};
