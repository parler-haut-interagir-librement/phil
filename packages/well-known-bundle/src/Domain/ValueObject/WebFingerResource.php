<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;

/**
 * The subject a WebFinger query asks about (RFC 7033): an `acct:` URI (RFC 7565), such as `acct:alice@example.com`, or an
 * `https:` URI, such as a profile page.
 *
 * An `acct:` URI is written back with its scheme and host in lowercase and its user part as given, percent-encoding
 * included; an `https:` URI keeps its path, query and fragment, its scheme and host in lowercase: `$uri` is the key
 * resolvers look a resource up by. Any other scheme is read too — a resource the bundle knows nothing of, so the query
 * finds nothing —, but text that is not an absolute URI, holds a space or a control character, or is longer than 2,048
 * bytes is refused.
 */
final readonly class WebFingerResource
{
    public const int MAX_LENGTH = 2048;

    public const string ACCT = 'acct';

    public const string HTTPS = 'https';

    private const string USER_PART = '/^(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})+$/D';

    private const string HOST = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*(?::\d{1,5})?$/D';

    private const string SCHEME = '/^([A-Za-z][A-Za-z0-9+.-]{0,31}):/';

    /**
     * @param string  $scheme the scheme, in lowercase
     * @param ?string $user   the user part of an `acct:` URI, percent-decoded; `null` for another scheme
     * @param ?string $host   the host, in lowercase, with its port when it has one; `null` for a scheme the bundle does not read
     */
    private function __construct(
        public string $uri,
        public string $scheme,
        public ?string $user,
        public ?string $host,
    ) {
    }

    /**
     * @throws InvalidValue when the text is not an absolute URI, or is an `acct:` or `https:` URI that does not hold together
     */
    public static function parse(string $resource): self
    {
        if ('' === $resource || \strlen($resource) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $resource) || 1 !== preg_match(self::SCHEME, $resource, $matches)) {
            throw InvalidValue::because('A WebFinger resource is an absolute URI without spaces, 2048 bytes at most.');
        }

        $scheme = strtolower($matches[1]);
        $rest = substr($resource, \strlen($matches[0]));

        return match ($scheme) {
            self::ACCT => self::account($rest),
            self::HTTPS => self::page($rest),
            default => new self($scheme.':'.$rest, $scheme, null, null),
        };
    }

    public function isAccount(): bool
    {
        return self::ACCT === $this->scheme;
    }

    private static function account(string $rest): self
    {
        $at = strrpos($rest, '@');

        if (false === $at) {
            throw InvalidValue::because('An acct: resource is "acct:" followed by a user, "@" and a host.');
        }

        $user = substr($rest, 0, $at);
        $host = strtolower(substr($rest, $at + 1));

        if (1 !== preg_match(self::USER_PART, $user) || 1 !== preg_match(self::HOST, $host)) {
            throw InvalidValue::because('An acct: resource is "acct:" followed by a user, "@" and a host.');
        }

        return new self(self::ACCT.':'.$user.'@'.$host, self::ACCT, rawurldecode($user), $host);
    }

    private static function page(string $rest): self
    {
        if (!str_starts_with($rest, '//')) {
            throw InvalidValue::because('An https: resource is "https://" followed by a host.');
        }

        $authority = substr($rest, 2, strcspn($rest, '/?#', 2));
        $host = strtolower($authority);

        if (str_contains($host, '@') || 1 !== preg_match(self::HOST, $host)) {
            throw InvalidValue::because('An https: resource is "https://" followed by a host.');
        }

        return new self(self::HTTPS.'://'.$host.substr($rest, 2 + \strlen($authority)), self::HTTPS, null, $host);
    }
}
