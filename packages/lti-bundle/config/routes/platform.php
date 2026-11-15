<?php

declare(strict_types=1);

use Phil\LtiBundle\Infrastructure\Controller\AgsController;
use Phil\LtiBundle\Infrastructure\Controller\NrpsController;
use Phil\LtiBundle\Infrastructure\Controller\PlatformLaunchController;
use Phil\LtiBundle\Infrastructure\Controller\TokenController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of the platform, mounted by the application under a prefix of its choice: the authorization endpoint
 * behind the firewall of its members, the token endpoint and the services stateless, outside any login form:
 *
 *     phil_lti_platform:
 *         resource: '@PhilLtiBundle/config/routes/platform.php'
 *         prefix: /lti/platform
 */
return static function (RoutingConfigurator $routes): void {
    $context = ['context' => '[^/]{1,255}'];
    $id = ['id' => '[0-9a-f]{32}'];

    $routes->add(PlatformLaunchController::AUTHORIZE_ROUTE, '/authorize')
        ->controller([PlatformLaunchController::class, 'authorize'])
        ->methods(['GET', 'POST']);

    $routes->add(PlatformLaunchController::DEEP_LINKING_ROUTE, '/deep-linking')
        ->controller([PlatformLaunchController::class, 'deepLinking'])
        ->methods(['POST']);

    $routes->add(TokenController::ROUTE, '/token')
        ->controller(TokenController::class)
        ->methods(['POST']);

    $routes->add(AgsController::LINE_ITEMS_ROUTE, '/contexts/{context}/lineitems')
        ->controller([AgsController::class, 'lineItems'])
        ->requirements($context)
        ->methods(['GET', 'POST']);

    $routes->add(AgsController::LINE_ITEM_ROUTE, '/contexts/{context}/lineitems/{id}')
        ->controller([AgsController::class, 'lineItem'])
        ->requirements($context + $id)
        ->methods(['GET', 'PUT', 'DELETE']);

    $routes->add(AgsController::SCORES_ROUTE, '/contexts/{context}/lineitems/{id}/scores')
        ->controller([AgsController::class, 'scores'])
        ->requirements($context + $id)
        ->methods(['POST']);

    $routes->add(AgsController::RESULTS_ROUTE, '/contexts/{context}/lineitems/{id}/results')
        ->controller([AgsController::class, 'results'])
        ->requirements($context + $id)
        ->methods(['GET']);

    $routes->add(NrpsController::ROUTE, '/contexts/{context}/memberships')
        ->controller(NrpsController::class)
        ->requirements($context)
        ->methods(['GET']);
};
