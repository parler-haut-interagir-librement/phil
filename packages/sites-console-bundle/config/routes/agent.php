<?php

declare(strict_types=1);

use Phil\SitesConsoleBundle\Infrastructure\Http\AgentController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The agent's endpoints, under the prefix the application imports them with, such as `/_sites-console`. They answer
 * the console only: each request is signed, and an unsigned one gets a 401.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_sites_console_agent_status', '/status')->controller([AgentController::class, 'status'])->methods(['GET']);
    $routes->add('phil_sites_console_agent_configuration', '/configuration')->controller([AgentController::class, 'configuration'])->methods(['POST']);
    $routes->add('phil_sites_console_agent_update', '/update')->controller([AgentController::class, 'update'])->methods(['POST']);
    $routes->add('phil_sites_console_agent_keys', '/keys')->controller([AgentController::class, 'keys'])->methods(['POST']);
};
