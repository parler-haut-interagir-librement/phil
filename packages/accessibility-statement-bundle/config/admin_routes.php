<?php

declare(strict_types=1);

use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration page of the statement, imported by the application under the firewall of its administration,
 * once `admin.enabled` is set:
 *
 *     # config/routes/phil_accessibility_statement_admin.yaml
 *     phil_accessibility_statement_admin:
 *         resource: '@PhilAccessibilityStatementBundle/config/admin_routes.php'
 *         prefix: /admin
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(StatementAdminController::ROUTE, '/accessibility-statement')
        ->controller([StatementAdminController::class, 'index'])
        ->methods(['GET', 'HEAD']);

    $routes->add(StatementAdminController::PUBLISH_ROUTE, '/accessibility-statement/publish')
        ->controller([StatementAdminController::class, 'publish'])
        ->methods(['POST']);
};
