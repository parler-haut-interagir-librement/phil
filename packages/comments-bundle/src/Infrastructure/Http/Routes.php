<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Http;

/**
 * The names of the endpoints, keyed as the controller's configuration names their URLs; those of one thread or one
 * comment are given with `__id__` in place of its identifier.
 */
final class Routes
{
    public const string ID = '__id__';

    public const array NAMES = [
        'threads' => 'phil_comments_threads',
        'open' => 'phil_comments_open',
        'reply' => 'phil_comments_reply',
        'resolve' => 'phil_comments_resolve',
        'reopen' => 'phil_comments_reopen',
        'accept' => 'phil_comments_accept',
        'reject' => 'phil_comments_reject',
        'edit' => 'phil_comments_edit',
        'delete' => 'phil_comments_delete',
    ];

    /**
     * The parameters each route needs to be generated with the placeholder.
     */
    public const array PARAMETERS = [
        'threads' => [],
        'open' => [],
        'reply' => ['thread' => self::ID],
        'resolve' => ['thread' => self::ID],
        'reopen' => ['thread' => self::ID],
        'accept' => ['thread' => self::ID],
        'reject' => ['thread' => self::ID],
        'edit' => ['comment' => self::ID],
        'delete' => ['comment' => self::ID],
    ];
}
