<?php

declare(strict_types=1);

use Phil\ApiKeysBundle\Infrastructure\Http\TokenPageController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The page where members manage their tokens, and where those the application allows manage the tokens of another
 * owner, imported by the application behind its signed-in firewall:
 *
 *     # config/routes/phil_api_keys.yaml
 *     phil_api_keys:
 *         resource: '@PhilApiKeysBundle/config/routes.php'
 *         prefix: /account/api-tokens
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(TokenPageController::ROUTE, '/')
        ->controller([TokenPageController::class, 'page'])
        ->methods(['GET', 'POST']);
    $routes->add(TokenPageController::REVOKE_ROUTE, '/{id}/revoke')
        ->controller([TokenPageController::class, 'revoke'])
        ->requirements(['id' => '[0-9A-Za-z]{12}'])
        ->methods(['POST']);
    $routes->add(TokenPageController::OWNER_ROUTE, '/owners/{ownerType}/{owner}')
        ->controller([TokenPageController::class, 'page'])
        ->requirements(['ownerType' => '[a-z][a-z0-9_]{0,31}', 'owner' => '[^/]{1,180}'])
        ->methods(['GET', 'POST']);
    $routes->add(TokenPageController::OWNER_REVOKE_ROUTE, '/owners/{ownerType}/{owner}/{id}/revoke')
        ->controller([TokenPageController::class, 'revoke'])
        ->requirements(['ownerType' => '[a-z][a-z0-9_]{0,31}', 'owner' => '[^/]{1,180}', 'id' => '[0-9A-Za-z]{12}'])
        ->methods(['POST']);
};
