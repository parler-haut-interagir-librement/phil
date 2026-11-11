<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Http;

/**
 * The names of the endpoints, keyed as the controller's configuration names their URLs.
 */
final class Routes
{
    public const array NAMES = [
        'acquire' => 'phil_edit_lock_acquire',
        'heartbeat' => 'phil_edit_lock_heartbeat',
        'release' => 'phil_edit_lock_release',
        'takeOver' => 'phil_edit_lock_take_over',
        'leave' => 'phil_edit_lock_leave',
        'presence' => 'phil_edit_lock_presence',
        'save' => 'phil_edit_lock_save',
        'remove' => 'phil_edit_lock_remove',
    ];
}
