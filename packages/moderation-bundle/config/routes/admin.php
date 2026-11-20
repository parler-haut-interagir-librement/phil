<?php

declare(strict_types=1);

use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the moderation, behind the application's firewall: import them under a prefix of the administration.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(AdminController::QUEUE_ROUTE, '/')->controller([AdminController::class, 'queue'])->methods(['GET']);
    $routes->add(AdminController::ENTRY_ROUTE, '/entry')->controller([AdminController::class, 'entry'])->methods(['GET']);
    $routes->add(AdminController::DECIDE_ROUTE, '/entry')->controller([AdminController::class, 'decide'])->methods(['POST']);
    $routes->add(AdminController::MEMBER_ROUTE, '/member')->controller([AdminController::class, 'member'])->methods(['GET']);
    $routes->add(AdminController::NOTE_ROUTE, '/member/notes')->controller([AdminController::class, 'note'])->methods(['POST']);
    $routes->add(AdminController::NOTE_REMOVE_ROUTE, '/member/notes/{id}/remove')
        ->controller([AdminController::class, 'removeNote'])
        ->requirements(['id' => AdminController::HEX])
        ->methods(['POST']);
};
