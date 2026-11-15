<?php

declare(strict_types=1);

use Phil\ModerationBundle\Infrastructure\Http\ReportController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The forms the pages of the site post to report a content, and to hide one for a trusted member.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ReportController::REPORT_ROUTE, '/report')->controller([ReportController::class, 'report'])->methods(['POST']);
    $routes->add(ReportController::HIDE_ROUTE, '/hide')->controller([ReportController::class, 'hide'])->methods(['POST']);
};
