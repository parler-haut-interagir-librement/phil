<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

/**
 * The address of a launch: the content's URL with the parameters of its specification added to its query, before its
 * fragment, encoded as RFC 3986 asks.
 */
final class LaunchUrl
{
    private function __construct()
    {
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function with(string $url, array $parameters): string
    {
        $fragment = '';
        $hash = strpos($url, '#');

        if (false !== $hash) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986).$fragment;
    }
}
