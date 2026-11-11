<?php

declare(strict_types=1);

use Phil\QrCodeBundle\Infrastructure\Http\QrCodeController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The QR codes served as images, at signed addresses that qr_code_url() writes; imported by the application:
 *
 *     # config/routes/phil_qr_code.yaml
 *     phil_qr_code:
 *         resource: '@PhilQrCodeBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(QrCodeController::ROUTE, '/qr-code.{format}')
        ->controller(QrCodeController::class)
        ->requirements(['format' => 'svg|png'])
        ->methods(['GET', 'HEAD']);
};
