<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The address the reports of a response go to: the one the configuration gives, or the route of the bundle. A path —
 * the route's, or one the configuration gives — is put on `public_origin`, the canonical address of the site, when it
 * is set, and is otherwise sent as it is, a path the browser resolves against the page it loaded. The `Host` header
 * of the request never chooses where the reports go. Addresses are read by `phil/url`.
 */
final readonly class ReportEndpoint
{
    public const string ROUTE = 'phil_csp_report';

    public const string OPTION = 'phil_browser_security.public_origin';

    /**
     * @param string|null $uri    the address of the reports — absolute, or a path of this site — instead of the route of
     *                            the bundle
     * @param string|null $origin the canonical address of the site, such as `https://example.com`
     */
    public function __construct(
        private ?UrlGeneratorInterface $urls = null,
        private bool $enabled = true,
        private ?string $uri = null,
        private ?string $origin = null,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * The origin, read by `phil/url`; null stays null.
     *
     * @throws InvalidConfigurationException when it is not an origin: no web address, or one with a path, a query or a
     *                                       fragment
     */
    public static function origin(?string $origin, bool $allowLocalhost): ?Url
    {
        if (null === $origin) {
            return null;
        }

        try {
            $url = Url::fromString($origin, $allowLocalhost);
        } catch (InvalidUrl $invalidUrl) {
            throw new InvalidConfigurationException(\sprintf('"%s" is the canonical address of the site, such as https://example.com: %s', self::OPTION, $invalidUrl->getMessage()), $invalidUrl->getCode(), previous: $invalidUrl);
        }

        if ('/' !== $url->path || null !== $url->query || null !== $url->fragment) {
            throw new InvalidConfigurationException(\sprintf('"%s" is an origin — a scheme, a host and a port — without path, query or fragment, such as https://example.com.', self::OPTION));
        }

        return $url;
    }

    public function uri(): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        if (null !== $this->uri) {
            return str_starts_with($this->uri, '/') ? $this->onOrigin($this->uri) : $this->uri;
        }

        try {
            $path = $this->urls?->generate(self::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_PATH);
        } catch (RouteNotFoundException) {
            return null;
        }

        return null === $path ? null : $this->onOrigin($path);
    }

    private function onOrigin(string $path): string
    {
        $origin = self::origin($this->origin, $this->allowLocalhost);

        return $origin instanceof Url ? Url::fromString(rtrim($origin->toString(), '/').$path, $this->allowLocalhost)->toString() : $path;
    }
}
