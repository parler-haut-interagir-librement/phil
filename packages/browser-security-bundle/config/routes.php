<?php

declare(strict_types=1);

use Phil\BrowserSecurityBundle\Infrastructure\Http\ContentSecurityPolicyListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ReportController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoint of the reports, `POST /_csp/report`, stateless: imported by the application, which may add a prefix.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ContentSecurityPolicyListener::REPORT_ROUTE, '/_csp/report')
        ->controller(ReportController::class)
        ->methods(['POST'])
        ->stateless();
};
