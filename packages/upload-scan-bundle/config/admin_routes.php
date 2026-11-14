<?php

declare(strict_types=1);

use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The quarantine's administration, imported by the application under a prefix its firewall protects:
 *
 *     # config/routes/phil_upload_scan_admin.yaml
 *     phil_upload_scan_admin:
 *         resource: '@PhilUploadScanBundle/config/admin_routes.php'
 *         prefix: /admin/quarantine
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(QuarantineAdminController::LIST_ROUTE, '/')->controller([QuarantineAdminController::class, 'list'])->methods(['GET']);
    $routes->add(QuarantineAdminController::DELETE_ROUTE, '/{id}/delete')->controller([QuarantineAdminController::class, 'delete'])->requirements(['id' => '[0-9a-zA-Z_-]{1,64}'])->methods(['POST']);
};
