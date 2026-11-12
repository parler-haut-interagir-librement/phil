<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Rendering;

use InvalidArgumentException;

/**
 * A normalized content as schema.org reads it: a `WebPage` by default, or the type a template asks for —
 * `Article`, `NewsArticle`, `BlogPosting` —, with its name (and headline, for an article), description, publication
 * date, image, keywords, address and language.
 */
final class ContentSchema
{
    /**
     * @param array<array-key, mixed> $content a normalized content
     *
     * @return array<string, mixed>
     */
    public static function of(array $content, string $type = 'WebPage', ?string $url = null, ?string $locale = null): array
    {
        if (1 !== preg_match('/^[A-Z][A-Za-z]{1,63}$/D', $type)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a schema.org type.', $type));
        }

        $name = self::text($content['seoTitle'] ?? null) ?? self::text($content['excerptTitle'] ?? null);
        $image = self::text($content['excerptImage'] ?? null);
        $keywords = \is_array($content['seoKeywords'] ?? null) ? array_filter($content['seoKeywords'], \is_string(...)) : [];

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $name,
            'headline' => 'WebPage' === $type ? null : $name,
            'description' => self::text($content['seoDescription'] ?? null) ?? self::text($content['excerptDescription'] ?? null),
            'datePublished' => self::text($content['workflowPublished'] ?? null),
            'image' => null !== $image && 1 === preg_match('{^https?://}', $image) ? $image : null,
            'keywords' => [] === $keywords ? null : implode(', ', $keywords),
            'url' => $url ?? self::text($content['seoCanonicalUrl'] ?? null),
            'inLanguage' => null === $locale ? null : str_replace('_', '-', $locale),
        ], static fn (mixed $value): bool => null !== $value);
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }
}
