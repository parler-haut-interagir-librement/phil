<?php

declare(strict_types=1);

use Phil\SchedulingPollBundle\Infrastructure\Http\AnswerController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;

/*
 * The pages of a respondent without an account, imported under a prefix of the application's choice:
 *
 *     phil_scheduling_poll:
 *         resource: '@PhilSchedulingPollBundle/config/routes.php'
 *         prefix: /scheduling-poll
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_scheduling_poll_answer', '/answer')
        ->controller([AnswerController::class, 'answer'])
        ->methods(['GET', 'POST']);
    $routes->add('phil_scheduling_poll_join', '/{poll}/join')
        ->controller([AnswerController::class, 'join'])
        ->requirements(['poll' => Requirement::UUID])
        ->methods(['GET', 'POST']);
};
