<?php

declare(strict_types=1);

use Phil\SurveyBundle\Infrastructure\Http\SurveyController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the questionnaires, under the prefix the application gives them:
 *
 *     phil_survey:
 *         resource: '@PhilSurveyBundle/config/routes.php'
 *         prefix: /survey
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(SurveyController::SHOW, '/{key}')->controller([SurveyController::class, 'show'])->methods(['GET'])->requirements(['key' => '[a-z0-9][a-z0-9_-]{0,63}']);
    $routes->add(SurveyController::SUBMIT, '/{key}')->controller([SurveyController::class, 'submit'])->methods(['POST'])->requirements(['key' => '[a-z0-9][a-z0-9_-]{0,63}']);
    $routes->add(SurveyController::ADDENDUM, '/{key}/addendum')->controller([SurveyController::class, 'addendum'])->methods(['POST'])->requirements(['key' => '[a-z0-9][a-z0-9_-]{0,63}']);
};
