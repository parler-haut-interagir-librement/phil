<?php

declare(strict_types=1);

namespace Phil\Url;

use Phil\Url\Exception\InvalidUrl;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * The hosts of the services an application runs beside it, as its configuration declares them: a name without a
 * domain, as a container network names a service (`whisper`, `gotenberg`), an IP address (`10.0.0.5`, `::1`, `[::1]`),
 * or any host name of a private zone. Each is kept as `ServiceUrl` compares it: in lowercase, in its ASCII form, an
 * IPv4 address written in full, an IPv6 address in brackets and compressed.
 *
 * A declared host is a host only: a port, a path, a user name or any other part is refused. The list is immutable and
 * holds no state of a request.
 */
final readonly class ServiceHosts
{
    /** At most this many hosts are declared: a configuration names its services, it does not list a network. */
    public const int MAX_HOSTS = 256;

    /** @var array<string, true> by normalised host */
    private array $hosts;

    public function __construct(string ...$hosts)
    {
        if (\count($hosts) > self::MAX_HOSTS) {
            throw InvalidUrl::tooManyServiceHosts(\count($hosts), self::MAX_HOSTS);
        }

        $normalised = [];

        foreach ($hosts as $host) {
            $normalised[$this->normalise($host)] = true;
        }

        $this->hosts = $normalised;
    }

    /**
     * Whether this host, as `Uri\WhatWg\Url::getAsciiHost()` gives it, is declared.
     */
    public function declares(string $host): bool
    {
        return isset($this->hosts[$host]);
    }

    /**
     * The declared hosts, normalised, in the order they were first declared.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->hosts);
    }

    private function normalise(string $host): string
    {
        $written = trim($host);
        // An IPv6 address is declared with or without its brackets.
        $written = str_contains($written, ':') && !str_starts_with($written, '[') ? '['.$written.']' : $written;

        // Only the characters of a host: no port after the address, no path, query, fragment or user name around it.
        if (1 !== preg_match('/^(?:\[[0-9A-Fa-f:.]+\]|[^\x00-\x20\x7F:\/\\\\?#@\[\]%]+)$/', $written)) {
            throw InvalidUrl::serviceHost($host);
        }

        $ascii = WhatWgUrl::parse('http://'.$written.'/')?->getAsciiHost();

        if (null === $ascii) {
            throw InvalidUrl::serviceHost($host);
        }

        // One trailing dot, the root of a fully qualified name, is dropped, as `ServiceUrl` drops it.
        return str_ends_with($ascii, '.') ? substr($ascii, 0, -1) : $ascii;
    }
}
