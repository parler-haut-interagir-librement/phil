<?php

declare(strict_types=1);

use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Phil\NotificationsBundle\Infrastructure\Remote\RemoteNotificationController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoint that receives the notifications of the other sites of a network, signed as Standard Webhooks, imported
 * by the application outside its login firewall: the signature is the authentication.
 *
 *     # config/routes/phil_notifications_remote.yaml
 *     phil_notifications_remote:
 *         resource: '@PhilNotificationsBundle/config/remote_routes.php'
 *         prefix: /notifications
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Routes::REMOTE, '/remote')->controller(RemoteNotificationController::class)->methods(['POST']);
};
