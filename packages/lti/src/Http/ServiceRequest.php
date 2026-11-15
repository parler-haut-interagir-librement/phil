<?php

declare(strict_types=1);

namespace Phil\Lti\Http;

use Phil\Url\Url;

/**
 * A request to a service of the other side.
 */
final readonly class ServiceRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public Url $url,
        public array $headers = [],
        public ?string $body = null,
    ) {
    }
}
