<?php

declare(strict_types=1);

use Phil\HelpdeskBundle\Infrastructure\Http\RequesterController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the requesters, imported by the application under the prefix of its choice, behind its firewall:
 *
 *     phil_helpdesk:
 *         resource: '@PhilHelpdeskBundle/config/routes.php'
 *         prefix: /help
 */
return static function (RoutingConfigurator $routes): void {
    $uuid = ['id' => RequesterController::UUID];

    $routes->add(RequesterController::REQUESTS_ROUTE, '/')
        ->controller([RequesterController::class, 'requests'])
        ->methods(['GET']);
    $routes->add(RequesterController::NEW_ROUTE, '/new')
        ->controller([RequesterController::class, 'new'])
        ->methods(['GET', 'POST']);
    $routes->add(RequesterController::REQUEST_ROUTE, '/{id}')
        ->controller([RequesterController::class, 'show'])
        ->requirements($uuid)
        ->methods(['GET']);
    $routes->add(RequesterController::ANSWER_ROUTE, '/{id}/answer')
        ->controller([RequesterController::class, 'answer'])
        ->requirements($uuid)
        ->methods(['POST']);
    $routes->add(RequesterController::WITHDRAW_ROUTE, '/{id}/withdraw')
        ->controller([RequesterController::class, 'withdraw'])
        ->requirements($uuid)
        ->methods(['POST']);
};
