<?php

declare(strict_types=1);

use Phil\EditLockBundle\Infrastructure\Http\EditLockController;
use Phil\EditLockBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The collaboration controller's endpoints, imported by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_edit_lock.yaml
 *     phil_edit_lock:
 *         resource: '@PhilEditLockBundle/config/routes.php'
 *         prefix: /_edit-lock
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Routes::NAMES['acquire'], '/acquire')->controller([EditLockController::class, 'acquire'])->methods(['POST']);
    $routes->add(Routes::NAMES['heartbeat'], '/heartbeat')->controller([EditLockController::class, 'heartbeat'])->methods(['POST']);
    $routes->add(Routes::NAMES['release'], '/release')->controller([EditLockController::class, 'release'])->methods(['POST']);
    $routes->add(Routes::NAMES['takeOver'], '/take-over')->controller([EditLockController::class, 'takeOver'])->methods(['POST']);
    $routes->add(Routes::NAMES['leave'], '/leave')->controller([EditLockController::class, 'leave'])->methods(['POST']);
    $routes->add(Routes::NAMES['presence'], '/presence')->controller([EditLockController::class, 'presence'])->methods(['GET']);
    $routes->add(Routes::NAMES['save'], '/save')->controller([EditLockController::class, 'save'])->methods(['POST']);
    $routes->add(Routes::NAMES['remove'], '/remove')->controller([EditLockController::class, 'remove'])->methods(['POST']);
};
