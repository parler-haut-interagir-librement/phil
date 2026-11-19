<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

use Phil\ShortLinkBundle\Application\Exception\InvalidCodec;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The domains that serve short links, in the order of the configuration: each host once, read by phil/url as a
 * browser reads it — in lower case, an international host in its ASCII form.
 *
 * A request is answered on a domain only when its host is one of them. A link is written on the first domain of its
 * locale, or else on the first domain without a locale, or else on the first domain.
 */
final readonly class ShortLinkDomains
{
    /** @var array<string, ShortLinkDomain> */
    private array $domains;

    /**
     * @param list<ShortLinkDomain> $domains
     *
     * @throws InvalidCodec when there is no domain, or a host is not a host name
     */
    public function __construct(array $domains, bool $allowLocalhost = false)
    {
        $byHost = [];

        foreach ($domains as $domain) {
            try {
                $url = Url::fromString($domain->scheme.'://'.$domain->host, allowLocalhost: $allowLocalhost);
            } catch (InvalidUrl) {
                throw InvalidCodec::because(\sprintf('The short link domain "%s" is not a host name.', $domain->host));
            }

            if ($url->toString() !== $url->scheme.'://'.$url->host.'/') {
                throw InvalidCodec::because(\sprintf('The short link domain "%s" is a host name alone, without a port or a path.', $domain->host));
            }

            $byHost[$url->host] ??= new ShortLinkDomain($url->host, $domain->locale, $url->scheme);
        }

        if ([] === $byHost) {
            throw InvalidCodec::because('Short links need at least one domain.');
        }

        $this->domains = $byHost;
    }

    /**
     * The domain of a request's host, none when the host is not one of the domains.
     */
    public function forHost(string $host): ?ShortLinkDomain
    {
        return $this->domains[strtolower($host)] ?? null;
    }

    public function forLocale(?string $locale): ShortLinkDomain
    {
        $shared = null;

        foreach ($this->domains as $domain) {
            if (null !== $locale && $domain->locale === $locale) {
                return $domain;
            }

            if (null === $domain->locale) {
                $shared ??= $domain;
            }
        }

        return $shared ?? array_first($this->domains);
    }

    public function isShortLinkHost(string $host): bool
    {
        return isset($this->domains[strtolower($host)]);
    }

    /**
     * The locales that have a domain of their own.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = [];

        foreach ($this->domains as $domain) {
            if (null !== $domain->locale) {
                $locales[$domain->locale] = $domain->locale;
            }
        }

        return array_values($locales);
    }

    /**
     * @return list<ShortLinkDomain>
     */
    public function all(): array
    {
        return array_values($this->domains);
    }
}
