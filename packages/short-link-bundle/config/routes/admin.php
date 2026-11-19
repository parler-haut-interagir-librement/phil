<?php

declare(strict_types=1);

use Phil\ShortLinkBundle\Infrastructure\Http\ShortLinkAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration of the short links, mounted by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_short_link.yaml
 *     phil_short_link_admin:
 *         resource: '@PhilShortLinkBundle/config/routes/admin.php'
 *         prefix: /admin/short-links
 */
return static function (RoutingConfigurator $routes): void {
    $id = ['id' => '[1-9]\d{0,17}'];

    $routes->add(ShortLinkAdminController::LIST_ROUTE, '/')
        ->controller([ShortLinkAdminController::class, 'list'])
        ->methods(['GET']);

    $routes->add(ShortLinkAdminController::CREATE_ROUTE, '/new')
        ->controller([ShortLinkAdminController::class, 'create'])
        ->methods(['GET', 'POST']);

    $routes->add(ShortLinkAdminController::EDIT_ROUTE, '/{id}/edit')
        ->controller([ShortLinkAdminController::class, 'edit'])
        ->requirements($id)
        ->methods(['GET', 'POST']);

    $routes->add('phil_short_link_admin_toggle', '/{id}/toggle')
        ->controller([ShortLinkAdminController::class, 'toggle'])
        ->requirements($id)
        ->methods(['POST']);

    $routes->add('phil_short_link_admin_delete', '/{id}/delete')
        ->controller([ShortLinkAdminController::class, 'delete'])
        ->requirements($id)
        ->methods(['POST']);

    $routes->add('phil_short_link_admin_qr_code', '/{id}/qr-code.{format}')
        ->controller([ShortLinkAdminController::class, 'qrCode'])
        ->requirements([...$id, 'format' => 'svg|png'])
        ->methods(['GET']);
};
