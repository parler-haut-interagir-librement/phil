<?php

declare(strict_types=1);

use Phil\AddressBundle\Infrastructure\Http\AddressFormatController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The format endpoint the address form's Stimulus controller reads, imported by the application:
 *
 *     # config/routes/phil_address.yaml
 *     phil_address:
 *         resource: '@PhilAddressBundle/config/routes.php'
 *         prefix: /address-format
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_address_format', '/{country}')
        ->controller(AddressFormatController::class)
        ->requirements(['country' => '[A-Za-z]{2}|__COUNTRY__'])
        ->methods(['GET']);
};
