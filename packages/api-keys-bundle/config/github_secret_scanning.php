<?php

declare(strict_types=1);

use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubSecretScanningController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoint GitHub's secret scanning posts the tokens it finds to, imported by the application once
 * `phil_api_keys.secret_scanning.github` is enabled, at the address registered with GitHub, outside any firewall that
 * requires a user:
 *
 *     # config/routes/phil_api_keys.yaml
 *     phil_api_keys_github_secret_scanning:
 *         resource: '@PhilApiKeysBundle/config/github_secret_scanning.php'
 *         prefix: /hooks/github/secret-scanning
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(GitHubSecretScanningController::ROUTE, '/')
        ->controller(GitHubSecretScanningController::class)
        ->methods(['POST'])
        ->stateless();
};
