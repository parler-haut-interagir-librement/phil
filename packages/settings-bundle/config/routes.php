<?php

declare(strict_types=1);

use Phil\SettingsBundle\Infrastructure\Http\Routes;
use Phil\SettingsBundle\Infrastructure\Http\SettingsController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The preferences, one section per page, imported by the application under a prefix of its choice; visitors reach
 * the settings kept for them, so the firewall lets them in:
 *
 *     # config/routes/phil_settings.yaml
 *     phil_settings:
 *         resource: '@PhilSettingsBundle/config/routes.php'
 *         prefix: /preferences
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Routes::PREFERENCES, '/{section}')
        ->controller([SettingsController::class, 'preferences'])
        ->methods(['GET', 'POST'])
        ->defaults(['section' => null])
        ->requirements(['section' => Routes::SECTION]);
};
