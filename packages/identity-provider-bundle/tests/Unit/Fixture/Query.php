<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

/**
 * The parameters of the query of an address, each a string, for the tests.
 */
final class Query
{
    private function __construct()
    {
    }

    /**
     * @return array<string, string>
     */
    public static function of(string $url): array
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $strings = [];

        foreach ($query as $name => $value) {
            if (\is_string($value)) {
                $strings[(string) $name] = $value;
            }
        }

        return $strings;
    }

    public static function get(string $url, string $name): string
    {
        return self::of($url)[$name] ?? '';
    }
}
