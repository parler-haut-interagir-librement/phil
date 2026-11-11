<?php

declare(strict_types=1);

use Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The admin screens of the flags, mounted by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_feature_flags.yaml
 *     phil_feature_flags_admin:
 *         resource: '@PhilFeatureFlagsBundle/config/routes/admin.php'
 *         prefix: /admin/flags
 */
return static function (RoutingConfigurator $routes): void {
    $name = '[a-z0-9]+(?:[._-][a-z0-9]+)*';

    $routes->add('phil_feature_flags_admin_list', '/')
        ->controller([FlagAdminController::class, 'list'])
        ->methods(['GET']);

    // `_new` is outside the flag-name alphabet, so no flag's page is ever shadowed by the creation form.
    $routes->add('phil_feature_flags_admin_create', '/_new')
        ->controller([FlagAdminController::class, 'create'])
        ->methods(['GET', 'POST']);

    $routes->add('phil_feature_flags_admin_show', '/{name}')
        ->controller([FlagAdminController::class, 'show'])
        ->requirements(['name' => $name])
        ->methods(['GET']);

    $routes->add('phil_feature_flags_admin_toggle', '/{name}/toggle')
        ->controller([FlagAdminController::class, 'toggle'])
        ->requirements(['name' => $name])
        ->methods(['POST']);

    $routes->add('phil_feature_flags_admin_edit', '/{name}/edit')
        ->controller([FlagAdminController::class, 'edit'])
        ->requirements(['name' => $name])
        ->methods(['GET', 'POST']);
};
