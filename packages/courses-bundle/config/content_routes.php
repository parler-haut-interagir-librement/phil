<?php

declare(strict_types=1);

use Phil\CoursesBundle\Infrastructure\Http\ContentController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The files of the imported packages, served on the content origin only, outside the application's firewall: a request
 * to another host is not found. An application whose web server serves the storage directory on the content origin
 * does not import these routes.
 *
 *     phil_courses_content:
 *         resource: '@PhilCoursesBundle/config/content_routes.php'
 *         prefix: /
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ContentController::ROUTE, '/{path}')
        ->controller([ContentController::class, 'serve'])
        ->requirements(['path' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/.+'])
        ->methods(['GET', 'HEAD']);
};
