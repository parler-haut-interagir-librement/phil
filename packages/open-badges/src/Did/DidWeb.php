<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Did;

use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * A `did:web` identifier and the HTTPS address of its DID document, as the did:web method specification gives it:
 * `did:web:example.com` at `https://example.com/.well-known/did.json`, `did:web:example.com:issuers:42` at
 * `https://example.com/issuers/42/did.json`, and a port written `%3A`, `did:web:example.com%3A8443`. The host is a name,
 * never an IP address nor `localhost`; a segment of the path is never `.` nor `..`, nor holds a slash.
 */
final readonly class DidWeb
{
    public const string PREFIX = 'did:web:';

    private function __construct(
        public string $did,
        public Url $url,
    ) {
    }

    public static function isDidWeb(string $identifier): bool
    {
        return str_starts_with($identifier, self::PREFIX);
    }

    /**
     * @throws InvalidCredential when the identifier is not a `did:web` this library resolves
     */
    public static function fromString(string $did): self
    {
        if (\strlen($did) > Url::MAX_LENGTH || 1 !== preg_match('/^did:web:([A-Za-z0-9.-]+)(?:%3[Aa]([1-9][0-9]{0,4}))?((?::(?:[A-Za-z0-9._~-]|%[0-9A-Fa-f]{2})+)*)$/D', $did, $match)) {
            throw InvalidCredential::because(\sprintf('"%s" is not a did:web identifier.', $did));
        }

        $segments = '' === $match[3] ? [] : explode(':', substr($match[3], 1));

        foreach ($segments as $segment) {
            $decoded = rawurldecode($segment);

            if ('.' === $decoded || '..' === $decoded || false !== strpbrk($decoded, "/\\?#\x00")) {
                throw InvalidCredential::because(\sprintf('The path of "%s" holds a segment a did:web does not.', $did));
            }
        }

        $address = 'https://'.$match[1].('' === $match[2] ? '' : ':'.$match[2]).'/'.([] === $segments ? '.well-known' : implode('/', $segments)).'/did.json';

        try {
            $url = Url::fromString($address);
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidCredential::because(\sprintf('"%s" names no host a did:web resolves: %s', $did, $invalidUrl->getMessage()));
        }

        if (strtolower($match[1]) !== $url->host) {
            throw InvalidCredential::because(\sprintf('"%s" names no host a did:web resolves.', $did));
        }

        return new self($did, $url);
    }
}
