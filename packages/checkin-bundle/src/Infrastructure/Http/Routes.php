<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Http;

/**
 * The names of the bundle's routes, which `config/routes.php` declares and the desk's configuration resolves to URLs.
 */
final readonly class Routes
{
    public const array NAMES = [
        'scan' => 'phil_checkin_scan',
        'manifest' => 'phil_checkin_manifest',
        'sync' => 'phil_checkin_sync',
    ];
}
