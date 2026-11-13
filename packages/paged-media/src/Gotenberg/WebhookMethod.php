<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

/**
 * The method of the request that uploads a result to a webhook.
 */
enum WebhookMethod: string
{
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
}
