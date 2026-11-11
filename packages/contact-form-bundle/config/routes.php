<?php

declare(strict_types=1);

use Phil\ContactFormBundle\Infrastructure\Http\ContactFormController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The contact page, imported by the application:
 *
 *     # config/routes/phil_contact_form.yaml
 *     phil_contact_form:
 *         resource: '@PhilContactFormBundle/config/routes.php'
 *         prefix: /contact     # or /nous-joindre, or with {_locale}
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_contact_form', '/')
        ->controller(ContactFormController::class)
        ->methods(['GET', 'POST']);
};
