<?php

declare(strict_types=1);

use Phil\CoursesBundle\Infrastructure\Http\CourseController;
use Phil\CoursesBundle\Infrastructure\Http\SecondChancesController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration page of the second chances, imported by the application under the prefix of its choice, behind
 * its firewall; the page itself asks for the role `phil_courses.admin.role`:
 *
 *     phil_courses_admin:
 *         resource: '@PhilCoursesBundle/config/admin_routes.php'
 *         prefix: /admin/courses
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(SecondChancesController::LIST_ROUTE, '/second-chances')
        ->controller([SecondChancesController::class, 'list'])
        ->methods(['GET']);
    $routes->add(SecondChancesController::DECIDE_ROUTE, '/second-chances/{retry}')
        ->controller([SecondChancesController::class, 'decide'])
        ->requirements(['retry' => CourseController::UUID])
        ->methods(['POST']);
};
