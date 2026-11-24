<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Routing;

use Override;
use Phil\SamlSpBundle\Application\Contract\EndpointUrls;
use Phil\Url\Url;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The addresses of the routes of the bundle, absolute, read by phil/url: `localhost` only when the configuration allows
 * it, in development.
 */
final readonly class RoutedEndpoints implements EndpointUrls
{
    public const string METADATA = 'phil_saml_sp_metadata';

    public const string LOGIN = 'phil_saml_sp_login';

    public const string ACS = 'phil_saml_sp_acs';

    public const string SLO = 'phil_saml_sp_slo';

    public function __construct(private UrlGeneratorInterface $router, private bool $allowLocalhost = false)
    {
    }

    #[Override]
    public function metadata(string $identityProvider): Url
    {
        return $this->url(self::METADATA, $identityProvider);
    }

    #[Override]
    public function assertionConsumerService(string $identityProvider): Url
    {
        return $this->url(self::ACS, $identityProvider);
    }

    #[Override]
    public function singleLogoutService(string $identityProvider): Url
    {
        return $this->url(self::SLO, $identityProvider);
    }

    private function url(string $route, string $identityProvider): Url
    {
        return Url::fromString($this->router->generate($route, ['idp' => $identityProvider], UrlGeneratorInterface::ABSOLUTE_URL), $this->allowLocalhost);
    }
}
