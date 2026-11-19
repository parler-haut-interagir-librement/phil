<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Routing;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\LinkOrigin;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * The links sent by email, generated while the router's context names `public_origin` — its scheme, host and port —
 * and given back to the request's context at once, so that nothing stays from one link to the next. The base path of
 * the application and the router's parameters, such as `_locale`, are kept. Without `public_origin`, the router's
 * context is the request's, whose host `framework.trusted_hosts` must then bound: the bundle refuses to boot in the
 * `prod` environment when neither is set.
 *
 * The origin and each link are read by `phil/url`: an `http` or `https` address, a host name — no IP address —, no
 * user, and, for the origin, no path, query or fragment. `localhost` is accepted only in debug mode.
 */
final readonly class RouterLinkOrigin implements LinkOrigin
{
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
    public static function origin(?string $origin, bool $allowLocalhost, string $option = 'phil_identity_provider.public_origin'): ?Url
    {
        if (null === $origin) {
            return null;
        }

        try {
            $url = Url::fromString($origin, $allowLocalhost);
        } catch (InvalidUrl $invalidUrl) {
            throw new InvalidConfigurationException(\sprintf('"%s" is the canonical address of the site, such as https://example.com: %s', $option, $invalidUrl->getMessage()), $invalidUrl->getCode(), previous: $invalidUrl);
        }

        if ('/' !== $url->path || null !== $url->query || null !== $url->fragment) {
            throw new InvalidConfigurationException(\sprintf('"%s" is an origin — a scheme, a host and a port — without path, query or fragment, such as https://example.com.', $option));
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
    public static function assertBound(?string $origin, string $environment, array $trustedHosts, string $option = 'phil_identity_provider.public_origin'): void
    {
        if (null === $origin && [] === $trustedHosts && 'prod' === $environment) {
            throw new InvalidConfigurationException(\sprintf('The links sent by email would take the host of the request: set "%s" to the canonical address of the site, or list the hosts it answers in framework.trusted_hosts.', $option));
        }
    }

    #[Override]
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
