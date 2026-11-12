<?php

declare(strict_types=1);

namespace Phil\SocialCards\Preview;

use Phil\SocialCards\Text;
use Uri\WhatWg\Url;

/**
 * What a link to another site is shown with, read from that page's tags: Open Graph first, then the card of X, then
 * the plain `<title>` and description. Every address is absolute HTTP or HTTPS on a public host name, read by
 * phil/url — resolved against the page's when relative, dropped when it is anything else, an IP address or
 * `localhost` included —, and every text is on one line.
 *
 * The address of the preview is the address of the page read. Its `og:url`, then its canonical link, replaces it only
 * when it names the same host and port: a page cannot show itself under the address of another site.
 */
final readonly class LinkPreview
{
    public const int MAX_TITLE = 300;

    public const int MAX_DESCRIPTION = 500;

    public function __construct(
        public string $url,
        public string $title,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $imageAlt = null,
        public ?string $siteName = null,
        public ?string $type = null,
        public ?string $locale = null,
    ) {
    }

    public static function fromTags(PageTags $tags): self
    {
        $page = Text::publicUrl($tags->url);
        $url = null === $page ? $tags->url : self::onHost(Text::resolve($tags->first('og:url'), $tags->url), $page) ?? self::onHost($tags->canonical, $page) ?? $page;
        $host = Url::parse($url)?->getUnicodeHost() ?? $url;

        return new self(
            $url,
            Text::line($tags->first('og:title', 'twitter:title') ?? $tags->title, self::MAX_TITLE) ?? $host,
            Text::line($tags->first('og:description', 'twitter:description', 'description'), self::MAX_DESCRIPTION),
            Text::resolve($tags->first('og:image', 'og:image:url', 'og:image:secure_url', 'twitter:image', 'twitter:image:src'), $tags->url),
            Text::line($tags->first('og:image:alt', 'twitter:image:alt'), 420),
            Text::line($tags->first('og:site_name'), 200) ?? $host,
            Text::line($tags->first('og:type'), 50),
            Text::line($tags->first('og:locale'), 20),
        );
    }

    /**
     * The address the page names for itself, when it is on the host of the page read.
     */
    private static function onHost(?string $url, string $page): ?string
    {
        return null !== $url && Text::onSameHost($url, $page) ? $url : null;
    }

    /**
     * @return array{url: string, title: string, description: string|null, image: string|null, image_alt: string|null, site_name: string|null, type: string|null, locale: string|null}
     */
    public function toArray(): array
    {
        return ['url' => $this->url, 'title' => $this->title, 'description' => $this->description, 'image' => $this->image, 'image_alt' => $this->imageAlt, 'site_name' => $this->siteName, 'type' => $this->type, 'locale' => $this->locale];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): ?self
    {
        $url = $values['url'] ?? null;
        $title = $values['title'] ?? null;

        $url = \is_string($url) ? Text::publicUrl($url) : null;

        if (null === $url || !\is_string($title)) {
            return null;
        }

        $string = static fn (string $key): ?string => \is_string($values[$key] ?? null) ? $values[$key] : null;
        $image = $string('image');

        return new self($url, $title, $string('description'), null === $image ? null : Text::publicUrl($image), $string('image_alt'), $string('site_name'), $string('type'), $string('locale'));
    }
}
