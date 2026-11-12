<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application;

use InvalidArgumentException;

/**
 * The site's search as schema.org reads it: a `WebSite` whose `potentialAction` is a `SearchAction`, so that an
 * engine can offer a search box that lands on the site's own results page.
 */
final class SearchActionSchema
{
    public const string PLACEHOLDER = '{search_term_string}';

    /**
     * @param string $resultsUrl the results page, with `{search_term_string}` where the words go:
     *                           `https://example.com/search?q={search_term_string}`
     *
     * @return array<string, mixed>
     */
    public static function of(string $siteUrl, string $resultsUrl, ?string $name = null): array
    {
        if (!str_contains($resultsUrl, self::PLACEHOLDER)) {
            throw new InvalidArgumentException(\sprintf('The results URL holds %s where the words go; "%s" does not.', self::PLACEHOLDER, $resultsUrl));
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'url' => $siteUrl,
            'name' => $name,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $resultsUrl],
                'query-input' => 'required name=search_term_string',
            ],
        ], static fn (mixed $value): bool => null !== $value);
    }
}
