<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Override;
use Phil\IdentityClientBundle\Application\Contract\ReturnToPolicy;
use Uri\WhatWg\Url;

/**
 * Accepts a path of the application, or an address on one of the declared origins.
 *
 * The candidate is read with the WHATWG parser, the one browsers follow, so that what is checked here is where the
 * browser goes. The answer is rebuilt from the parsed address and never echoes the candidate.
 */
final readonly class DeclaredOriginsReturnToPolicy implements ReturnToPolicy
{
    private const string LOCAL_BASE = 'https://local.invalid/';

    private const string LOCAL_ORIGIN = 'https://local.invalid';

    private const int MAX_LENGTH = 2048;

    private const string UNSAFE_CHARACTERS = '/[\x00-\x20\x7f\\\\]/';

    /** @var array<string, string> */
    private array $origins;

    /**
     * @param list<string> $origins the declared origins; one that cannot be read matches nothing
     */
    public function __construct(array $origins)
    {
        $known = [];
        foreach ($origins as $origin) {
            $parsed = Url::parse($origin);
            $key = $parsed instanceof Url ? self::originOf($parsed) : null;
            if (null !== $key) {
                $known[$key] = $key;
            }
        }

        $this->origins = $known;
    }

    /**
     * Whether a value is an origin an application may declare: a bare `https` origin, or `http` on a loopback host.
     */
    public static function isDeclarable(string $origin): bool
    {
        $parsed = Url::parse($origin);
        if (!$parsed instanceof Url || null === ($key = self::originOf($parsed)) || rtrim($origin, '/') !== $key) {
            return false;
        }

        return 'https' === $parsed->getScheme() || \in_array($parsed->getAsciiHost(), ['localhost', '127.0.0.1', '[::1]'], true);
    }

    #[Override]
    public function resolve(string $candidate): ?string
    {
        if ('' === $candidate || \strlen($candidate) > self::MAX_LENGTH || 1 === preg_match(self::UNSAFE_CHARACTERS, $candidate)) {
            return null;
        }

        $url = Url::parse($candidate, new Url(self::LOCAL_BASE));
        if (!$url instanceof Url || null !== $url->getUsername()) {
            return null;
        }

        $origin = self::originOf($url);
        if (self::LOCAL_ORIGIN === $origin) {
            return str_starts_with($candidate, '/') ? $this->localTarget($url) : null;
        }

        return null !== $origin && isset($this->origins[$origin]) ? $url->toAsciiString() : null;
    }

    /**
     * A path the normalization turned into `//host` would be an address on another host, so it is refused here.
     */
    private function localTarget(Url $url): ?string
    {
        $path = $url->getPath();
        if (str_starts_with($path, '//')) {
            return null;
        }

        $query = $url->getQuery();

        return null === $query ? $path : $path.'?'.$query;
    }

    private static function originOf(Url $url): ?string
    {
        $scheme = $url->getScheme();
        $host = $url->getAsciiHost();
        if (null === $host || !\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $port = $url->getPort();

        return $scheme.'://'.$host.(null === $port ? '' : ':'.$port);
    }
}
