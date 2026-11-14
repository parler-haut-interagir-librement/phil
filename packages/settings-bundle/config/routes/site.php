<?php

declare(strict_types=1);

use Phil\SettingsBundle\Infrastructure\Http\Routes;
use Phil\SettingsBundle\Infrastructure\Http\SettingsController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The site's settings, one section per page, imported by the application under the prefix of its administration,
 * behind its firewall; each setting is shown to the users of its role only:
 *
 *     # config/routes/phil_settings_site.yaml
 *     phil_settings_site:
 *         resource: '@PhilSettingsBundle/config/routes/site.php'
 *         prefix: /admin/settings
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Routes::SITE, '/{section}')
        ->controller([SettingsController::class, 'site'])
        ->methods(['GET', 'POST'])
        ->defaults(['section' => null])
        ->requirements(['section' => Routes::SECTION]);
};
