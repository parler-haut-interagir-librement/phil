<?php

declare(strict_types=1);

use Phil\IncidentModeBundle\Infrastructure\Http\SuspensionPage;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The public page that says what is suspended now and until when, and the same notice as a fragment, mounted by the
 * application under a prefix of its choice:
 *
 *     # config/routes/phil_incident_mode.yaml
 *     phil_incident_mode_status:
 *         resource: '@PhilIncidentModeBundle/config/routes/status.php'
 *         prefix: /status
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(SuspensionPage::STATUS_ROUTE, '/')
        ->controller([SuspensionPage::class, 'status'])
        ->methods(['GET', 'HEAD']);

    $routes->add(SuspensionPage::NOTICE_ROUTE, '/notice')
        ->controller([SuspensionPage::class, 'notice'])
        ->methods(['GET', 'HEAD']);
};
