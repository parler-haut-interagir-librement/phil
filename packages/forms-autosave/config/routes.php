<?php

declare(strict_types=1);

use Phil\FormsAutosave\Infrastructure\Http\DraftController;
use Phil\FormsAutosave\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoints of the server's drafts, imported by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_forms_autosave.yaml
 *     phil_forms_autosave:
 *         resource: '@PhilFormsAutosaveBundle/config/routes.php'
 *         prefix: /_autosave
 */
return static function (RoutingConfigurator $routes): void {
    $key = ['key' => '[A-Za-z0-9_-]{16,64}'];
    $routes->add(Routes::DRAFT, '/drafts/{key}')->controller([DraftController::class, 'read'])->methods(['GET'])->requirements($key);
    $routes->add(Routes::SAVE, '/drafts/{key}')->controller([DraftController::class, 'save'])->methods(['PUT'])->requirements($key);
    $routes->add(Routes::DISCARD, '/drafts/{key}')->controller([DraftController::class, 'discard'])->methods(['DELETE'])->requirements($key);
};
