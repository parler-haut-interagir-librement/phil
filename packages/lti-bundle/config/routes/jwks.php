<?php

declare(strict_types=1);

use Phil\LtiBundle\Infrastructure\Controller\JwksController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The key set of the application, mounted once whether it is a tool, a platform or both:
 *
 *     phil_lti_jwks:
 *         resource: '@PhilLtiBundle/config/routes/jwks.php'
 *         prefix: /lti
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(JwksController::ROUTE, '/jwks')
        ->controller(JwksController::class)
        ->methods(['GET']);
};
