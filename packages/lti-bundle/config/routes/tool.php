<?php

declare(strict_types=1);

use Phil\LtiBundle\Infrastructure\Controller\ToolController;
use Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of the tool, mounted by the application under a prefix of its choice; the launch route sits behind a
 * firewall with the bundle's authenticator:
 *
 *     # config/routes/phil_lti.yaml
 *     phil_lti_tool:
 *         resource: '@PhilLtiBundle/config/routes/tool.php'
 *         prefix: /lti
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ToolController::LOGIN_ROUTE, '/login')
        ->controller([ToolController::class, 'login'])
        ->methods(['GET', 'POST']);

    $routes->add(LtiLaunchAuthenticator::ROUTE, '/launch')
        ->controller([ToolController::class, 'launch'])
        ->methods(['POST']);
};
