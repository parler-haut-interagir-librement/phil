<?php

declare(strict_types=1);

use Phil\EmailAddressBundle\Infrastructure\Http\EmailDomainsController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The lists the email field's Stimulus controller reads, imported by the application:
 *
 *     # config/routes/phil_email_address.yaml
 *     phil_email_address:
 *         resource: '@PhilEmailAddressBundle/config/routes.php'
 *         prefix: /email-address
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_email_address_domains', '/domains.json')
        ->controller(EmailDomainsController::class)
        ->methods(['GET', 'HEAD']);
};
