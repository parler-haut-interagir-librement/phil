<?php

declare(strict_types=1);

namespace Phil\SocialCards;

use DateTimeInterface;

/**
 * The tags of a page's head from its share metadata: its canonical link and description, Open Graph, its `article:`
 * tags for an article, the card of X and `fediverse:creator`, in that order, each written once.
 */
final class MetaTags
{
    /**
     * @return list<MetaTag>
     */
    public static function of(ShareMetadata $page, ?TwitterCard $card = null): array
    {
        $card ??= $page->image instanceof ShareImage ? TwitterCard::SummaryLargeImage : TwitterCard::Summary;
        $image = $page->image;

        return array_values(array_filter([
            MetaTag::link('canonical', $page->url),
            self::named('description', $page->description),
            MetaTag::property('og:title', $page->title),
            MetaTag::property('og:type', $page->type->value),
            MetaTag::property('og:url', $page->url),
            self::property('og:description', $page->description),
            self::property('og:site_name', $page->siteName),
            self::property('og:locale', $page->locale),
            ...array_map(static fn (string $locale): MetaTag => MetaTag::property('og:locale:alternate', $locale), $page->alternateLocales),
            ...self::image($image),
            ...self::article($page),
            MetaTag::name('twitter:card', $card->value),
            MetaTag::name('twitter:title', $page->title),
            self::named('twitter:description', $page->description),
            self::named('twitter:image', $image?->url),
            self::named('twitter:image:alt', $image?->alt),
            self::named('twitter:site', $page->twitterSite),
            self::named('twitter:creator', $page->twitterCreator),
            self::named('fediverse:creator', $page->fediverseCreator),
        ]));
    }

    /**
     * The tags as HTML, one per line.
     *
     * @param list<MetaTag> $tags
     */
    public static function html(array $tags): string
    {
        return implode("\n", array_map(static fn (MetaTag $tag): string => $tag->html(), $tags));
    }

    /**
     * @return list<MetaTag|null>
     */
    private static function image(?ShareImage $image): array
    {
        if (!$image instanceof ShareImage) {
            return [];
        }

        return [
            MetaTag::property('og:image', $image->url),
            self::property('og:image:type', $image->type),
            self::property('og:image:width', null === $image->width ? null : (string) $image->width),
            self::property('og:image:height', null === $image->height ? null : (string) $image->height),
            self::property('og:image:alt', $image->alt),
        ];
    }

    /**
     * @return list<MetaTag|null>
     */
    private static function article(ShareMetadata $page): array
    {
        if (ShareType::Article !== $page->type) {
            return [];
        }

        return [
            self::property('article:published_time', $page->publishedTime?->format(DateTimeInterface::ATOM)),
            self::property('article:modified_time', $page->modifiedTime?->format(DateTimeInterface::ATOM)),
            self::property('article:author', $page->author),
        ];
    }

    private static function property(string $key, ?string $content): ?MetaTag
    {
        return null === $content ? null : MetaTag::property($key, $content);
    }

    private static function named(string $key, ?string $content): ?MetaTag
    {
        return null === $content ? null : MetaTag::name($key, $content);
    }
}
