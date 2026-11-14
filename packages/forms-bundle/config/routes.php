<?php

declare(strict_types=1);

use Phil\FormsBundle\Infrastructure\Http\FormController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The page of each form, imported by the application:
 *
 *     # config/routes/phil_forms.yaml
 *     phil_forms:
 *         resource: '@PhilFormsBundle/config/routes.php'
 *         prefix: /forms
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_forms_form', '/{key}')
        ->controller(FormController::class)
        ->requirements(['key' => '[a-z0-9][a-z0-9-]{0,63}'])
        ->methods(['GET', 'POST']);
};
