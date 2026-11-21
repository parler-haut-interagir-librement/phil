<?php

declare(strict_types=1);

use Phil\NotificationsBundle\Infrastructure\Http\DigestUnsubscribeController;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The signed link that stops a recipient's digest, imported by the application outside its login firewall: the
 * signature is the authentication, and a mail client posts to it without cookies (RFC 8058).
 *
 *     # config/routes/phil_notifications_digest.yaml
 *     phil_notifications_digest:
 *         resource: '@PhilNotificationsBundle/config/digest_routes.php'
 *         prefix: /notifications
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Routes::DIGEST_UNSUBSCRIBE, '/digest/unsubscribe/{recipient}')
        ->controller(DigestUnsubscribeController::class)
        ->methods(['GET', 'HEAD', 'POST'])
        ->requirements(['recipient' => '[A-Za-z0-9][A-Za-z0-9_.:\-]{0,189}']);
};
