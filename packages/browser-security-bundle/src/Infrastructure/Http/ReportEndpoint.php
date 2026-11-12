<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The address the reports of a response go to: the one the configuration gives, or the route of the bundle.
 */
final readonly class ReportEndpoint
{
    public const string ROUTE = 'phil_csp_report';

    /**
     * @param string|null $uri the address of the reports — absolute, or a path of this host — instead of the route of
     *                         the bundle
     */
    public function __construct(
        private ?UrlGeneratorInterface $urls = null,
        private bool $enabled = true,
        private ?string $uri = null,
    ) {
    }

    public function uri(Request $request): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        if (null !== $this->uri) {
            return str_starts_with($this->uri, '/') ? $request->getSchemeAndHttpHost().$this->uri : $this->uri;
        }

        try {
            return $this->urls?->generate(self::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
