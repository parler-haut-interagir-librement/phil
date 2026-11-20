<?php

declare(strict_types=1);

use Phil\OrganizationsBundle\Infrastructure\Http\UnitAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration pages of the units, imported by the application under the prefix of its choice, behind its
 * firewall; each page asks the member signed in to be a member of the organization, and each change checks their
 * permissions:
 *
 *     phil_organizations_units:
 *         resource: '@PhilOrganizationsBundle/config/routes/units.php'
 *         prefix: /admin/organizations
 */
return static function (RoutingConfigurator $routes): void {
    $id = '[A-Za-z0-9][A-Za-z0-9_.-]{0,127}';
    $unit = ['unit' => $id];

    $routes->add(UnitAdminController::TREE_ROUTE, '/{organization}/units')
        ->controller([UnitAdminController::class, 'tree'])
        ->requirements(['organization' => $id])
        ->methods(['GET']);
    $routes->add(UnitAdminController::CREATE_ROUTE, '/{organization}/units')
        ->controller([UnitAdminController::class, 'create'])
        ->requirements(['organization' => $id])
        ->methods(['POST']);
    $routes->add(UnitAdminController::UNIT_ROUTE, '/units/{unit}')
        ->controller([UnitAdminController::class, 'show'])
        ->requirements($unit)
        ->methods(['GET']);

    foreach (['rename', 'move', 'archive', 'restore'] as $action) {
        $routes->add(UnitAdminController::UNIT_ROUTE.'_'.$action, '/units/{unit}/'.$action)
            ->controller([UnitAdminController::class, $action])
            ->requirements($unit)
            ->methods(['POST']);
    }

    $routes->add(UnitAdminController::UNIT_ROUTE.'_member_add', '/units/{unit}/members')
        ->controller([UnitAdminController::class, 'addMember'])
        ->requirements($unit)
        ->methods(['POST']);
    $routes->add(UnitAdminController::UNIT_ROUTE.'_member_remove', '/units/{unit}/members/{member}/remove')
        ->controller([UnitAdminController::class, 'removeMember'])
        ->requirements($unit + ['member' => $id])
        ->methods(['POST']);
};
