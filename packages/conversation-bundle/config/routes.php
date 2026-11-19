<?php

declare(strict_types=1);

use Phil\ConversationBundle\Infrastructure\Http\ConversationController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the conversations, imported by the application under the prefix of its choice, behind its firewall:
 *
 *     phil_conversation:
 *         resource: '@PhilConversationBundle/config/routes.php'
 *         prefix: /messages
 */
return static function (RoutingConfigurator $routes): void {
    $uuid = ['id' => ConversationController::UUID];

    $routes->add(ConversationController::INBOX_ROUTE, '/')
        ->controller([ConversationController::class, 'inbox'])
        ->methods(['GET']);
    $routes->add(ConversationController::SETTINGS_ROUTE, '/settings')
        ->controller([ConversationController::class, 'settings'])
        ->methods(['GET', 'POST']);
    $routes->add(ConversationController::START_ROUTE, '/new/{participant}')
        ->controller([ConversationController::class, 'start'])
        ->methods(['GET', 'POST']);
    $routes->add(ConversationController::THREAD_ROUTE, '/{id}')
        ->controller([ConversationController::class, 'thread'])
        ->requirements($uuid)
        ->methods(['GET']);
    $routes->add(ConversationController::MESSAGES_ROUTE, '/{id}/messages')
        ->controller([ConversationController::class, 'messages'])
        ->requirements($uuid)
        ->methods(['GET']);
    $routes->add(ConversationController::REPLY_ROUTE, '/{id}/reply')
        ->controller([ConversationController::class, 'reply'])
        ->requirements($uuid)
        ->methods(['POST']);
    $routes->add(ConversationController::MUTE_ROUTE, '/{id}/mute')
        ->controller([ConversationController::class, 'mute'])
        ->requirements($uuid)
        ->methods(['POST']);
    $routes->add(ConversationController::LEAVE_ROUTE, '/{id}/leave')
        ->controller([ConversationController::class, 'leave'])
        ->requirements($uuid)
        ->methods(['POST']);
};
