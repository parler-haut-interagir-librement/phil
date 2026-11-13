<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\Manifest;

/**
 * How the service worker answers a request: from the network, falling back on the cache; from the cache, falling back
 * on the network; or from the network only, as if there were no service worker.
 */
enum CacheStrategy: string
{
    case NetworkFirst = 'network-first';
    case CacheFirst = 'cache-first';
    case NetworkOnly = 'network-only';
}
