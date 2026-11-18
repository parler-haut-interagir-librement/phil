<?php

declare(strict_types=1);

use Phil\CoursesBundle\Infrastructure\Http\Cmi5Controller;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoints the cmi5 assignable units talk to, imported by the application under the prefix of its choice and
 * left outside its firewall: each request carries the token of its session, not the learner's cookies.
 *
 *     phil_courses_cmi5:
 *         resource: '@PhilCoursesBundle/config/cmi5_routes.php'
 *         prefix: /cmi5
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Cmi5Controller::FETCH_ROUTE, '/fetch/{session}')
        ->controller([Cmi5Controller::class, 'fetch'])
        ->requirements(['session' => '[0-9a-f-]{36}'])
        ->methods(['GET', 'POST']);
    $routes->add(Cmi5Controller::ENDPOINT_ROUTE, '/xapi/')
        ->controller([Cmi5Controller::class, 'about'])
        ->methods(['GET']);
    $routes->add(Cmi5Controller::ABOUT_ROUTE, '/xapi/about')
        ->controller([Cmi5Controller::class, 'about'])
        ->methods(['GET']);
    $routes->add(Cmi5Controller::STATEMENTS_ROUTE, '/xapi/statements')
        ->controller([Cmi5Controller::class, 'statements'])
        ->methods(['GET', 'PUT', 'POST']);
    $routes->add(Cmi5Controller::STATE_ROUTE, '/xapi/activities/state')
        ->controller([Cmi5Controller::class, 'state'])
        ->methods(['GET', 'PUT', 'POST', 'DELETE']);
    $routes->add(Cmi5Controller::AGENT_PROFILE_ROUTE, '/xapi/agents/profile')
        ->controller([Cmi5Controller::class, 'agentProfile'])
        ->methods(['GET', 'PUT', 'POST', 'DELETE']);
    $routes->add('phil_courses_xapi_preflight', '/{path}')
        ->controller([Cmi5Controller::class, 'preflight'])
        ->requirements(['path' => '(fetch|xapi)/.*'])
        ->methods(['OPTIONS']);
};
