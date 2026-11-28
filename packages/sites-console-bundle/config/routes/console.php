<?php

declare(strict_types=1);

use Phil\SitesConsoleBundle\Infrastructure\Http\ConsoleController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The screens of the console, under the prefix the application imports them with, such as `/admin/sites`, behind
 * its firewall.
 */
return static function (RoutingConfigurator $routes): void {
    $id = ['id' => '[A-Za-z0-9-]{1,64}'];

    $routes->add('phil_sites_console_dashboard', '/')->controller([ConsoleController::class, 'dashboard'])->methods(['GET']);
    $routes->add('phil_sites_console_pull_all', '/pull')->controller([ConsoleController::class, 'pullAll'])->methods(['POST']);
    $routes->add('phil_sites_console_versions', '/versions')->controller([ConsoleController::class, 'versions'])->methods(['GET']);
    $routes->add('phil_sites_console_journal', '/journal')->controller([ConsoleController::class, 'journal'])->methods(['GET']);
    $routes->add('phil_sites_console_site', '/sites/{id}')->controller([ConsoleController::class, 'site'])->methods(['GET'])->requirements($id);
    $routes->add('phil_sites_console_pull', '/sites/{id}/pull')->controller([ConsoleController::class, 'pull'])->methods(['POST'])->requirements($id);
    $routes->add('phil_sites_console_pushes', '/pushes')->controller([ConsoleController::class, 'pushes'])->methods(['GET']);
    $routes->add('phil_sites_console_push', '/pushes/{id}')->controller([ConsoleController::class, 'push'])->methods(['GET'])->requirements($id);
    $routes->add('phil_sites_console_deliver', '/pushes/{id}/deliver')->controller([ConsoleController::class, 'deliver'])->methods(['POST'])->requirements($id);
    $routes->add('phil_sites_console_updates', '/updates')->controller([ConsoleController::class, 'updates'])->methods(['GET']);
    $routes->add('phil_sites_console_update', '/updates/{id}')->controller([ConsoleController::class, 'update'])->methods(['GET'])->requirements($id);
    $routes->add('phil_sites_console_run', '/updates/{id}/run')->controller([ConsoleController::class, 'run'])->methods(['POST'])->requirements($id);
};
