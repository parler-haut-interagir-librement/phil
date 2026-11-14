<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

/**
 * The names of the endpoints, keyed as the controller's configuration names their URLs.
 */
final class Routes
{
    public const array NAMES = [
        'unread' => 'phil_notifications_unread',
        'read' => 'phil_notifications_read',
        'readAll' => 'phil_notifications_read_all',
        'archive' => 'phil_notifications_archive',
    ];

    public const string REMOTE = 'phil_notifications_remote';

    public const array PUSH = [
        'subscribe' => 'phil_notifications_push_subscribe',
        'unsubscribe' => 'phil_notifications_push_unsubscribe',
    ];
}
