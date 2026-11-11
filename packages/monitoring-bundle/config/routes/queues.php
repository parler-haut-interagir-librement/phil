<?php

declare(strict_types=1);

use Phil\MonitoringBundle\Infrastructure\Http\QueuesController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The page of the Messenger queues, imported by the application behind its firewall:
 *
 *     # config/routes/phil_monitoring.yaml
 *     phil_monitoring_queues:
 *         resource: '@PhilMonitoringBundle/config/routes/queues.php'
 *         prefix: /admin/queues
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(QueuesController::INDEX_ROUTE, '/')
        ->controller([QueuesController::class, 'index'])
        ->methods(['GET']);
    $routes->add(QueuesController::RETRY_ROUTE, '/{transport}/{id}/retry')
        ->controller([QueuesController::class, 'retry'])
        ->requirements(['transport' => QueuesController::TRANSPORT, 'id' => QueuesController::MESSAGE_ID])
        ->methods(['POST']);
    $routes->add(QueuesController::REMOVE_ROUTE, '/{transport}/{id}/remove')
        ->controller([QueuesController::class, 'remove'])
        ->requirements(['transport' => QueuesController::TRANSPORT, 'id' => QueuesController::MESSAGE_ID])
        ->methods(['POST']);
};
