<?php

declare(strict_types=1);

use Phil\NotificationsBundle\Infrastructure\Http\CentreController;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoints of the notification centre, imported by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_notifications.yaml
 *     phil_notifications:
 *         resource: '@PhilNotificationsBundle/config/routes.php'
 *         prefix: /notifications
 */
return static function (RoutingConfigurator $routes): void {
    $id = ['id' => '[A-Za-z0-9][A-Za-z0-9_.:\-]{0,189}'];

    $routes->add(Routes::NAMES['unread'], '/unread')->controller([CentreController::class, 'unread'])->methods(['GET']);
    $routes->add(Routes::NAMES['readAll'], '/read-all')->controller([CentreController::class, 'readAll'])->methods(['POST']);
    $routes->add(Routes::NAMES['read'], '/{id}/read')->controller([CentreController::class, 'read'])->methods(['POST'])->requirements($id);
    $routes->add(Routes::NAMES['archive'], '/{id}/archive')->controller([CentreController::class, 'archive'])->methods(['POST'])->requirements($id);
};
