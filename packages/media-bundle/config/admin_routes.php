<?php

declare(strict_types=1);

use Phil\MediaBundle\Infrastructure\Admin\MediaAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The file explorer, imported by the application under a prefix its firewall protects:
 *
 *     # config/routes/phil_media_admin.yaml
 *     phil_media_admin:
 *         resource: '@PhilMediaBundle/config/admin_routes.php'
 *         prefix: /admin/media
 */
return static function (RoutingConfigurator $routes): void {
    $id = ['id' => '[0-9a-f-]{36}'];

    $routes->add(MediaAdminController::INDEX_ROUTE, '/')->controller([MediaAdminController::class, 'index'])->methods(['GET']);
    $routes->add('phil_media_admin_upload', '/upload')->controller([MediaAdminController::class, 'upload'])->methods(['POST']);
    $routes->add('phil_media_admin_collection', '/collections')->controller([MediaAdminController::class, 'createCollection'])->methods(['POST']);
    $routes->add('phil_media_admin_rename', '/{id}/rename')->controller([MediaAdminController::class, 'rename'])->requirements($id)->methods(['POST']);
    $routes->add('phil_media_admin_move', '/{id}/move')->controller([MediaAdminController::class, 'move'])->requirements($id)->methods(['POST']);
    $routes->add('phil_media_admin_delete', '/{id}/delete')->controller([MediaAdminController::class, 'delete'])->requirements($id)->methods(['POST']);
};
