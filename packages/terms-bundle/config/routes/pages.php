<?php

declare(strict_types=1);

use Phil\TermsBundle\Infrastructure\Http\AcceptanceController;
use Phil\TermsBundle\Infrastructure\Http\PolicyController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The acceptance page and the public pages of the policies, mounted by the application under a prefix of its choice:
 *
 *     # config/routes/phil_terms.yaml
 *     phil_terms:
 *         resource: '@PhilTermsBundle/config/routes/pages.php'
 *         prefix: /terms
 */
return static function (RoutingConfigurator $routes): void {
    $policy = ['policy' => '[a-z][a-z0-9_-]{0,63}'];
    $number = ['number' => '[1-9]\d{0,5}'];

    $routes->add(AcceptanceController::ROUTE, '/accept')
        ->controller([AcceptanceController::class, 'accept'])
        ->methods(['GET', 'POST']);

    $routes->add(PolicyController::CURRENT_ROUTE, '/{policy}')
        ->controller([PolicyController::class, 'current'])
        ->requirements($policy)
        ->methods(['GET']);

    $routes->add(PolicyController::VERSION_ROUTE, '/{policy}/v{number}')
        ->controller([PolicyController::class, 'version'])
        ->requirements($policy + $number)
        ->methods(['GET']);

    $routes->add(PolicyController::CHANGES_ROUTE, '/{policy}/v{number}/changes/{from}')
        ->controller([PolicyController::class, 'changes'])
        ->requirements($policy + $number + ['from' => '[1-9]\d{0,5}'])
        ->methods(['GET']);
};
