<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\ValueObject;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The origin of a site that embeds a page — scheme, host and port, as a browser compares them —, read by phil/url: no
 * credentials, no IP address, no host without a dot, `localhost` only when allowed. Written as an origin, it has no
 * path, query nor fragment: `https://partner.example`, `http://localhost:8000`.
 */
final readonly class Origin
{
    private function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
    ) {
    }

    /**
     * @throws InvalidEmbed when the text is no address, or an address with a path, a query or a fragment
     */
    public static function fromString(string $text, bool $allowLocalhost = false): self
    {
        try {
            $url = Url::fromString($text, $allowLocalhost);
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidEmbed::because(\sprintf('"%s" is not an origin: %s', $text, $invalidUrl->getMessage()));
        }

        if ('/' !== $url->path || null !== $url->query || null !== $url->fragment || str_ends_with(rtrim($text), '/')) {
            throw InvalidEmbed::because(\sprintf('"%s" is not an origin: an origin has no path, query nor fragment.', $text));
        }

        return self::of($url);
    }

    /**
     * The origin of an address.
     */
    public static function of(Url $url): self
    {
        return new self($url->scheme, $url->host, $url->port);
    }

    public function toString(): string
    {
        return $this->scheme.'://'.$this->host.(null === $this->port ? '' : ':'.$this->port);
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }
}
