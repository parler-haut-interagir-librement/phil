<?php

declare(strict_types=1);

use Phil\IncidentModeBundle\Infrastructure\Http\IncidentAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration of the incident mode, mounted by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_incident_mode.yaml
 *     phil_incident_mode_admin:
 *         resource: '@PhilIncidentModeBundle/config/routes/admin.php'
 *         prefix: /admin/incidents
 */
return static function (RoutingConfigurator $routes): void {
    $id = ['id' => IncidentAdminController::ID];

    $routes->add(IncidentAdminController::INDEX_ROUTE, '/')
        ->controller([IncidentAdminController::class, 'index'])
        ->methods(['GET']);

    $routes->add(IncidentAdminController::START_ROUTE, '/start')
        ->controller([IncidentAdminController::class, 'start'])
        ->methods(['POST']);

    $routes->add(IncidentAdminController::EXTEND_ROUTE, '/{id}/extend')
        ->controller([IncidentAdminController::class, 'extend'])
        ->requirements($id)
        ->methods(['POST']);

    $routes->add(IncidentAdminController::LIFT_ROUTE, '/{id}/lift')
        ->controller([IncidentAdminController::class, 'lift'])
        ->requirements($id)
        ->methods(['POST']);
};
