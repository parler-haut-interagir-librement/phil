<?php

declare(strict_types=1);

use Phil\NotificationsBundle\Infrastructure\Http\PushSubscriptionController;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoints of Web Push, imported with `web_push` enabled, under a prefix of the application's choice, behind its
 * firewall:
 *
 *     # config/routes/phil_notifications_push.yaml
 *     phil_notifications_push:
 *         resource: '@PhilNotificationsBundle/config/push_routes.php'
 *         prefix: /notifications
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Routes::PUSH['subscribe'], '/push/subscribe')->controller([PushSubscriptionController::class, 'subscribe'])->methods(['POST']);
    $routes->add(Routes::PUSH['unsubscribe'], '/push/unsubscribe')->controller([PushSubscriptionController::class, 'unsubscribe'])->methods(['POST']);
};
