<?php

declare(strict_types=1);

use Phil\ModerationBundle\Infrastructure\Http\JournalController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The public journal of the moderation.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(JournalController::ROUTE, '/')->controller(JournalController::class)->methods(['GET']);
};
