<?php

declare(strict_types=1);

use Phil\TermsBundle\Infrastructure\Http\TermsAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration of the terms, mounted by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_terms_admin.yaml
 *     phil_terms_admin:
 *         resource: '@PhilTermsBundle/config/routes/admin.php'
 *         prefix: /admin/terms
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(TermsAdminController::LIST_ROUTE, '/')
        ->controller([TermsAdminController::class, 'list'])
        ->methods(['GET']);

    $routes->add(TermsAdminController::PUBLISH_ROUTE, '/{policy}/publish')
        ->controller([TermsAdminController::class, 'publish'])
        ->requirements(['policy' => '[a-z][a-z0-9_-]{0,63}'])
        ->methods(['GET', 'POST']);
};
