<?php

declare(strict_types=1);

namespace Phil\Url;

use Override;
use Phil\Url\Exception\InvalidUrl;
use Stringable;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * The address of a service the application runs beside it — a transcription server at `http://whisper:8080`, a PDF
 * renderer at `http://gotenberg:3000` —, whose host `Url` refuses: a name without a domain, or an IP address. It is
 * read only for a host the application declares in `ServiceHosts`, from its configuration; any other host is refused,
 * a public one included.
 *
 * Every other rule of `Url` holds: `http` or `https`, no user name nor password, no port 0, the path, the query and the
 * fragment normalised by the WHATWG URL Standard, 2048 characters at most. A `ServiceUrl` is never a `Url`: an address
 * a member, an editor or another site gives is read by `Url::fromString()`, which keeps refusing these hosts, and a
 * client of public sites never receives a service address by mistake.
 */
final readonly class ServiceUrl implements Stringable
{
    private function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
        public string $path,
        public ?string $query,
        public ?string $fragment,
    ) {
    }

    /**
     * @throws InvalidUrl when the text is no address, or its host is not declared
     */
    public static function fromString(string $text, ServiceHosts $hosts): self
    {
        return self::of(AddressReader::parse($text), $hosts);
    }

    /**
     * An address relative to this one, `/inference` or `v1/models?limit=5`, resolved as a browser resolves a link, on
     * the same scheme, host and port: one that leaves them, `//other:8080/`, is refused.
     *
     * @throws InvalidUrl when the reference leaves this service, or is no address
     */
    public function resolve(string $reference): self
    {
        $resolved = \strlen($reference) <= Url::MAX_LENGTH ? WhatWgUrl::parse($reference, WhatWgUrl::parse($this->toString())) : null;

        if (!$resolved instanceof WhatWgUrl) {
            throw InvalidUrl::notAUrl($reference);
        }

        $parsed = AddressReader::parse($resolved->toAsciiString());
        $host = self::hostOf($parsed);

        if ($parsed->getScheme() !== $this->scheme || $host !== $this->host || $parsed->getPort() !== $this->port) {
            throw InvalidUrl::leavesService($reference, $this->origin());
        }

        return self::build($parsed, $host);
    }

    /**
     * The scheme, the host and the port: `http://whisper:8080`.
     */
    public function origin(): string
    {
        return $this->scheme.'://'.$this->host.(null === $this->port ? '' : ':'.$this->port);
    }

    public function toString(): string
    {
        return AddressReader::write($this->scheme, $this->host, $this->port, $this->path, $this->query, $this->fragment);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    private static function of(WhatWgUrl $parsed, ServiceHosts $hosts): self
    {
        $host = self::hostOf($parsed);

        if (!$hosts->declares($host)) {
            throw InvalidUrl::undeclaredServiceHost($host);
        }

        return self::build($parsed, $host);
    }

    /**
     * The ASCII host, one trailing dot dropped, as `ServiceHosts` keeps it.
     */
    private static function hostOf(WhatWgUrl $parsed): string
    {
        $host = (string) $parsed->getAsciiHost();

        return str_ends_with($host, '.') ? substr($host, 0, -1) : $host;
    }

    private static function build(WhatWgUrl $parsed, string $host): self
    {
        $url = new self(
            $parsed->getScheme(),
            $host,
            $parsed->getPort(),
            $parsed->getPath(),
            AddressReader::nonEmpty($parsed->getQuery()),
            AddressReader::nonEmpty($parsed->getFragment()),
        );
        AddressReader::checkLength($url->toString());

        return $url;
    }
}
