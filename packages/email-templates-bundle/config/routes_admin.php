<?php

declare(strict_types=1);

use Phil\EmailTemplatesBundle\Infrastructure\Http\AdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration of the email templates, mounted by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_email_templates_admin.yaml
 *     phil_email_templates_admin:
 *         resource: '@PhilEmailTemplatesBundle/config/routes_admin.php'
 *         prefix: /admin/email-templates
 */
return static function (RoutingConfigurator $routes): void {
    $key = '[a-z][a-z0-9_.-]{0,63}';
    $locale = '[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?';

    $routes->add(AdminController::INDEX_ROUTE, '/')
        ->controller([AdminController::class, 'index'])
        ->methods(['GET']);
    $routes->add(AdminController::EDIT_ROUTE, '/{key}/{locale}')
        ->controller([AdminController::class, 'edit'])
        ->requirements(['key' => $key, 'locale' => $locale])
        ->methods(['GET', 'POST']);
    $routes->add(AdminController::FRAME_ROUTE, '/{key}/{locale}/frame')
        ->controller([AdminController::class, 'frame'])
        ->requirements(['key' => $key, 'locale' => $locale])
        ->methods(['GET']);
};
