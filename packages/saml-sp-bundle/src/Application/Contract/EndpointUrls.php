<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application\Contract;

use Phil\Url\Url;

/**
 * The absolute addresses of the routes of one identity provider: its metadata, its assertion consumer service and its
 * logout service.
 */
interface EndpointUrls
{
    public function metadata(string $identityProvider): Url;

    public function assertionConsumerService(string $identityProvider): Url;

    public function singleLogoutService(string $identityProvider): Url;
}
