<?php

declare(strict_types=1);

use Phil\CoursesBundle\Infrastructure\Http\CourseController;
use Phil\CoursesBundle\Infrastructure\Http\SecondChancesController;
use Phil\CoursesBundle\Infrastructure\Http\TrainingController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration pages — the second chances, and the training overdue or due soon with the transcripts of the
 * learners —, imported by the application under the prefix of its choice, behind its firewall; the pages themselves
 * ask for the roles `phil_courses.admin.role` and `phil_courses.training.manager_role`:
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
    $routes->add(TrainingController::TEAM_ROUTE, '/training')
        ->controller([TrainingController::class, 'team'])
        ->methods(['GET']);
    $routes->add(TrainingController::LEARNER_TRANSCRIPT_ROUTE, '/transcripts/{learner}/{_format}')
        ->controller([TrainingController::class, 'learnerTranscript'])
        ->defaults(['_format' => 'html'])
        ->requirements(['learner' => '[A-Za-z0-9][A-Za-z0-9._:\-]{0,189}', '_format' => implode('|', TrainingController::FORMATS)])
        ->methods(['GET']);
};
