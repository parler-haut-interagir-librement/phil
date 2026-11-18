<?php

declare(strict_types=1);

use Phil\CoursesBundle\Infrastructure\Http\CourseController;
use Phil\CoursesBundle\Infrastructure\Http\ScormController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the courses, imported by the application under the prefix of its choice, behind its firewall:
 *
 *     phil_courses:
 *         resource: '@PhilCoursesBundle/config/routes.php'
 *         prefix: /courses
 */
return static function (RoutingConfigurator $routes): void {
    $course = ['course' => CourseController::UUID];
    $activity = $course + ['activity' => CourseController::UUID];

    $routes->add(ScormController::COMMIT_ROUTE, '/scorm/commit')
        ->controller([ScormController::class, 'commit'])
        ->methods(['POST']);
    $routes->add(CourseController::OUTLINE_ROUTE, '/{course}')
        ->controller([CourseController::class, 'outline'])
        ->requirements($course)
        ->methods(['GET']);
    $routes->add(CourseController::ENROLL_ROUTE, '/{course}/enroll')
        ->controller([CourseController::class, 'enroll'])
        ->requirements($course)
        ->methods(['POST']);
    $routes->add(CourseController::LAUNCH_ROUTE, '/{course}/activities/{activity}/launch')
        ->controller([CourseController::class, 'launch'])
        ->requirements($activity)
        ->methods(['POST']);
    $routes->add(CourseController::COMPLETE_ROUTE, '/{course}/activities/{activity}/complete')
        ->controller([CourseController::class, 'complete'])
        ->requirements($activity)
        ->methods(['POST']);
    $routes->add(CourseController::RETRY_ROUTE, '/{course}/activities/{activity}/retry')
        ->controller([CourseController::class, 'retry'])
        ->requirements($activity)
        ->methods(['POST']);
};
