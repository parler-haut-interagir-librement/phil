<?php

declare(strict_types=1);

use Phil\ContentBundle\Infrastructure\Http\ContentAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration of the contents, mounted by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_content_admin.yaml
 *     phil_content_admin:
 *         resource: '@PhilContentBundle/config/routes_admin.php'
 *         prefix: /admin/contents
 */
return static function (RoutingConfigurator $routes): void {
    $reference = '[a-z][a-z0-9_]{0,63}:[A-Za-z0-9_-]{1,64}';
    $locale = '[A-Za-z_]{2,12}';

    $routes->add(ContentAdminController::LIST_ROUTE, '/')
        ->controller([ContentAdminController::class, 'list'])
        ->methods(['GET']);
    $routes->add(ContentAdminController::CREATE_ROUTE, '/new/{resourceKey}')
        ->controller([ContentAdminController::class, 'create'])
        ->requirements(['resourceKey' => '[a-z][a-z0-9_]{0,63}'])
        ->methods(['GET', 'POST']);
    $routes->add(ContentAdminController::EDIT_ROUTE, '/{reference}/{locale}')
        ->controller([ContentAdminController::class, 'edit'])
        ->requirements(['reference' => $reference, 'locale' => $locale])
        ->methods(['GET', 'POST']);
    $routes->add(ContentAdminController::AUTOSAVE_ROUTE, '/{reference}/{locale}/autosave')
        ->controller([ContentAdminController::class, 'autosave'])
        ->requirements(['reference' => $reference, 'locale' => $locale])
        ->methods(['POST']);
};
