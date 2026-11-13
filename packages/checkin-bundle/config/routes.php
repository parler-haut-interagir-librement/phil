<?php

declare(strict_types=1);

use Phil\CheckinBundle\Infrastructure\Http\CheckinController;
use Phil\CheckinBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The desk controller's endpoints, imported by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_checkin.yaml
 *     phil_checkin:
 *         resource: '@PhilCheckinBundle/config/routes.php'
 *         prefix: /_checkin
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Routes::NAMES['scan'], '/scan')->controller([CheckinController::class, 'scan'])->methods(['POST']);
    $routes->add(Routes::NAMES['manifest'], '/manifest')->controller([CheckinController::class, 'manifest'])->methods(['GET']);
    $routes->add(Routes::NAMES['sync'], '/sync')->controller([CheckinController::class, 'sync'])->methods(['POST']);
};
