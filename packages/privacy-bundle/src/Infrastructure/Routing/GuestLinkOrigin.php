<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Routing;

use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * The origin of the links sent by email to people without an account: `public_origin`, the canonical address of the
 * site, so that the `Host` header of the request that asked for a link never chooses where the link goes. The link is
 * generated while the router's context names that origin — its scheme, host and port — and the request's context is
 * given back at once; the base path of the application and the router's parameters are kept. Without `public_origin`,
 * the router's context is the request's, whose host `framework.trusted_hosts` must then bound: the bundle refuses to
 * boot in the `prod` environment when the requests without an account send their links by email and neither is set.
 *
 * The origin and each link are read by `phil/url`: an `http` or `https` address, a host name — no IP address —, no
 * user, and, for the origin, no path, query or fragment. `localhost` is accepted only in debug mode.
 */
final readonly class GuestLinkOrigin
{
    public const string OPTION = 'phil_privacy.public_origin';

    public function __construct(
        private RequestContextAwareInterface $router,
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

    /**
     * Refuses to boot in the `prod` environment when the links would take the host of the request unchecked: neither
     * `public_origin` nor `framework.trusted_hosts` is set.
     *
     * @param array<string> $trustedHosts the patterns of `framework.trusted_hosts`, as `Request::getTrustedHosts()` gives them
     *
     * @throws InvalidConfigurationException
     */
    public static function assertBound(?string $origin, string $environment, array $trustedHosts): void
    {
        if (null === $origin && [] === $trustedHosts && 'prod' === $environment) {
            throw new InvalidConfigurationException(\sprintf('The links of the requests without an account would take the host of the request: set "%s" to the canonical address of the site, or list the hosts it answers in framework.trusted_hosts.', self::OPTION));
        }
    }

    /**
     * The absolute address `$generate` builds with the router, on the canonical origin.
     *
     * @param callable(): string $generate generates the absolute address of a route with the router of the application
     */
    public function link(callable $generate): string
    {
        $origin = self::origin($this->origin, $this->allowLocalhost);

        if (!$origin instanceof Url) {
            return $generate();
        }

        $request = $this->router->getContext();
        $context = new RequestContext($request->getBaseUrl(), 'GET', $origin->host, $origin->scheme, 'http' === $origin->scheme ? $origin->port ?? 80 : 80, 'https' === $origin->scheme ? $origin->port ?? 443 : 443);
        $context->setParameters($request->getParameters());

        $this->router->setContext($context);

        try {
            $link = $generate();
        } finally {
            $this->router->setContext($request);
        }

        return Url::fromString($link, $this->allowLocalhost)->toString();
    }
}
