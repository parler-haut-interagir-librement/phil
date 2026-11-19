<?php

declare(strict_types=1);

use Phil\HelpdeskBundle\Infrastructure\Http\AgentController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the agents, imported by the application under the prefix of its choice, behind its firewall; with
 * EasyAdminBundle, the same pages are also grafted on each dashboard:
 *
 *     phil_helpdesk_agents:
 *         resource: '@PhilHelpdeskBundle/config/routes_agents.php'
 *         prefix: /admin/helpdesk
 */
return static function (RoutingConfigurator $routes): void {
    $uuid = ['id' => AgentController::UUID];

    $routes->add(AgentController::DESK_ROUTE, '/')
        ->controller([AgentController::class, 'desk'])
        ->methods(['GET']);
    $routes->add(AgentController::TICKET_ROUTE, '/{id}')
        ->controller([AgentController::class, 'show'])
        ->requirements($uuid)
        ->methods(['GET']);
    $routes->add(AgentController::REPLY_ROUTE, '/{id}/reply')
        ->controller([AgentController::class, 'reply'])
        ->requirements($uuid)
        ->methods(['POST']);
    $routes->add(AgentController::ASSIGN_ROUTE, '/{id}/assign')
        ->controller([AgentController::class, 'assign'])
        ->requirements($uuid)
        ->methods(['POST']);
    $routes->add(AgentController::TRANSITION_ROUTE, '/{id}/transition/{transition}')
        ->controller([AgentController::class, 'transition'])
        ->requirements($uuid + ['transition' => AgentController::TRANSITION])
        ->methods(['POST']);
    $routes->add(AgentController::MOVE_ROUTE, '/{id}/move')
        ->controller([AgentController::class, 'move'])
        ->requirements($uuid)
        ->methods(['POST']);
};
