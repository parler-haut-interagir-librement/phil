<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Fixtures;

use Override;
use Phil\SamlSpBundle\Application\Contract\EndpointUrls;
use Phil\Url\Url;

/**
 * The routes of the bundle under `https://app.example.com/saml`, as the router of the test application writes them.
 */
final class FixedEndpoints implements EndpointUrls
{
    #[Override]
    public function metadata(string $identityProvider): Url
    {
        return Url::fromString('https://app.example.com/saml/'.$identityProvider.'/metadata');
    }

    #[Override]
    public function assertionConsumerService(string $identityProvider): Url
    {
        return Url::fromString('https://app.example.com/saml/'.$identityProvider.'/acs');
    }

    #[Override]
    public function singleLogoutService(string $identityProvider): Url
    {
        return Url::fromString('https://app.example.com/saml/'.$identityProvider.'/slo');
    }
}
