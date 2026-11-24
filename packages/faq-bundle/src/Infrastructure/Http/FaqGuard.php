<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Http;

use Phil\FaqBundle\Application\Exception\VoteRefused;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What a vote or a counted search has to pass: Symfony's stateless CSRF check of the token `phil_faq`, which reads the
 * origin of the request and needs neither a session nor a cookie; and the page a plain form comes back to, which is a
 * path of this site and never another site.
 */
final readonly class FaqGuard
{
    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_faq';

    private const int MAX_PATH = 2_000;

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws VoteRefused when the check does not pass
     */
    public function assertTrusted(string $token): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw VoteRefused::untrustedRequest();
        }
    }

    /**
     * The path a plain form comes back to: the one it gives when it is a path of this site, `/` followed by anything
     * but `/` or `\`, without control characters; the fallback otherwise.
     */
    public static function localPath(string $path, string $fallback): string
    {
        if ('' === $path || \strlen($path) > self::MAX_PATH || '/' !== $path[0] || 1 === preg_match('#^/[/\\\\]|[\x00-\x20\x7F\\\\]#', $path)) {
            return $fallback;
        }

        return $path;
    }

    /**
     * The path with a parameter added to its query, and a fragment.
     */
    public static function withParameter(string $path, string $name, string $value, string $fragment): string
    {
        $path = preg_replace('/#.*/s', '', $path) ?? $path;

        return $path.(str_contains($path, '?') ? '&' : '?').rawurlencode($name).'='.rawurlencode($value).'#'.rawurlencode($fragment);
    }
}
