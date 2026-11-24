<?php

declare(strict_types=1);

use Phil\SamlSpBundle\Infrastructure\Controller\SamlController;
use Phil\SamlSpBundle\Infrastructure\Routing\RoutedEndpoints;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of each identity provider, by its key: the metadata of the service provider, the start of a sign-in, the
 * assertion consumer service and the logout service.
 */
return static function (RoutingConfigurator $routes): void {
    $key = ['idp' => '[a-z0-9][a-z0-9_-]{0,63}'];

    $routes->add(RoutedEndpoints::METADATA, '/{idp}/metadata')->controller([SamlController::class, 'metadata'])->methods(['GET'])->requirements($key);
    $routes->add(RoutedEndpoints::LOGIN, '/{idp}/login')->controller([SamlController::class, 'login'])->methods(['GET'])->requirements($key);
    $routes->add(RoutedEndpoints::ACS, '/{idp}/acs')->controller([SamlController::class, 'assertionConsumerService'])->methods(['POST'])->requirements($key);
    $routes->add(RoutedEndpoints::SLO, '/{idp}/slo')->controller([SamlController::class, 'singleLogout'])->methods(['GET', 'POST'])->requirements($key);
};
