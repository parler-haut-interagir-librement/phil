<?php

declare(strict_types=1);

use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The page of the statement, in English at /accessibility and in French at /accessibilite, imported by the application:
 *
 *     # config/routes/phil_accessibility_statement.yaml
 *     phil_accessibility_statement:
 *         resource: '@PhilAccessibilityStatementBundle/config/routes.php'
 *
 * Each version stays at its own address: /accessibility/v2, /accessibilite/v2.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(StatementController::ROUTE, ['en' => '/accessibility', 'fr' => '/accessibilite'])
        ->controller([StatementController::class, 'current'])
        ->methods(['GET', 'HEAD']);

    $routes->add(StatementController::VERSION_ROUTE, ['en' => '/accessibility/v{version}', 'fr' => '/accessibilite/v{version}'])
        ->controller([StatementController::class, 'version'])
        ->requirements(['version' => '[1-9]\d{0,5}'])
        ->methods(['GET', 'HEAD']);
};
