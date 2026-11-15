<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

/**
 * Parameters added to a registered address, after those its query already holds and before its fragment, as the
 * code, the state and the errors go back to a client.
 */
final class QueryString
{
    private function __construct()
    {
    }

    /**
     * The address with parameters added to its query, after those it already has.
     *
     * @param array<string, string> $parameters
     */
    public static function append(string $uri, array $parameters): string
    {
        $fragment = '';
        $hash = strpos($uri, '#');

        if (false !== $hash) {
            $fragment = substr($uri, $hash);
            $uri = substr($uri, 0, $hash);
        }

        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986).$fragment;
    }
}
